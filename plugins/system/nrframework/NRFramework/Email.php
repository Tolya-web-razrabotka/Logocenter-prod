<?php 

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\CMS\Language\Text;
use Tassos\Framework\Functions;
use Tassos\Framework\URLHelper;
use Tassos\Framework\Security\SafePath;
use Tassos\Vendor\Pelago\Emogrifier\CssInliner;
use Tassos\Vendor\Pelago\Emogrifier\HtmlProcessor\HtmlNormalizer;

/**
 *  Framework Emailer
 */
class Email
{
    /**
     *  Indicates the last error
     *
     *  @var  string
     */
    public $error;

    /**
     *  Email Object
     *
     *  @var  email data to be sent
     */
    private $email;

    /**
     *  Required elements for a valid email object
     *
     *  @var  array
     */
    private $requiredKeys = [
        'from_email',
        'from_name',
        'recipient',
        'subject',
        'body'
    ];

    /**
     *  Class constructor
     */
    public function __construct($email)
    {
        $this->email = $email;
    }

    /**
     *  Validates Email Object
     *
     *  @param   array  $email  The email object
     *
     *  @return  boolean        Returns true if the email object is valid
     */
    public function validate()
    {
        // Validate email object
        if (!$this->email || !is_array($this->email) || !count($this->email))
        {
            $this->setError('Invalid email object.');
            return;
        }

        // Check for missing properties
        foreach ($this->requiredKeys as $key)
        {
            if (!isset($this->email[$key]) || empty($this->email[$key]))
            {
                $this->setError("The $key field is either missing or invalid.");
                return;
            }
        }

        // Validate recipient email addresses.
        $this->email['recipient'] = Functions::makeArray($this->email['recipient']);

        foreach ($this->email['recipient'] as $recipient)
        {
            if (!$this->validateEmailAddress($recipient))
            {
                $this->setError("Invalid recipient email address: $recipient");
                return;
            }
        }

        // Validate sender email address
        if (!$this->validateEmailAddress($this->email['from_email']))
        {
            $this->setError('Invalid sender email address: ' . $this->email['from_email']);
            return;
        }

        $this->email['bcc'] = isset($this->email['bcc']) ? Functions::makeArray($this->email['bcc']) : [];
        $this->email['cc']  = isset($this->email['cc']) ? Functions::makeArray($this->email['cc']) : [];

        // Convert special HTML entities back to characters on non text-only properties.
        // For instance, the subject line of an email is not parsed as HTML, it's just pure text. 
        // Because of this an HTML entity like &amp; it will be displayed as encoded.
        // To prevent this from happening we need decode the values.
        if (!is_scalar($this->email['subject']))
        {
            $this->setError('The Subject is not valid.');
            return;
        }
        $this->email['subject'] = htmlspecialchars_decode($this->email['subject']);

        if (!is_scalar($this->email['from_name']))
        {
            $this->setError('The From Name is not valid.');
            return;
        }
        $this->email['from_name'] = htmlspecialchars_decode($this->email['from_name']);

        if (isset($this->email['reply_to_name']) && is_scalar($this->email['reply_to_name']))
        {
            $this->email['reply_to_name'] = htmlspecialchars_decode($this->email['reply_to_name']);
        }

        return true;
    }

    /**
     *  Sending emails
     *
     *  @param   array  $email  The mail objecta
     *
     *  @return  mixed          Returns true on success. Throws exeption on fail.
     */
    public function send()
    {
        // Proceed only if Mail Sending is enabled.
        if (!Factory::getConfig()->get('mailonline'))
        {
            $this->error = Text::_('NR_ERROR_EMAIL_IS_DISABLED');
            return;
        }

        // Validate first the email object
        if (!$this->validate($this->email))
        {
            return;
        }

        $defaults = [
            'cssInliner' => true,
        ];

        $email  = array_merge($defaults, $this->email);
        $mailer = Factory::getMailer();
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'quoted-printable';

        // Email Sender
        $mailer->setSender([
            $email['from_email'],
            $email['from_name']
        ]);

        // Reply-to
        if (isset($email['reply_to']) && !empty($email['reply_to']))
        {
            $name = (isset($email['reply_to_name']) && !empty($email['reply_to_name'])) ? $email['reply_to_name'] : '';
            
            $reply_to_addresses = array_filter(array_map('trim', explode(',', $email['reply_to'])));

            foreach($reply_to_addresses as $reply_to_address)
            {
                $mailer->addReplyTo($reply_to_address, $name);
            }
        }

        $body = $email['body'];

        // Convert all relative paths found in <a> and <img> elements to absolute URLs
        $body = URLHelper::relativePathsToAbsoluteURLs($body);

        // Apply CSS inline styles if enabled
        // The CSS inliner will automatically extract and apply all <style> blocks found in the HTML as inline styles.
        // Additionally, if custom CSS is provided in the 'css' option, it will also be applied as inline styles.
        if ($email['cssInliner'])
        {
            // Normalize the HTML content to ensure proper structure
            $body = HtmlNormalizer::fromHtml($body)->render();

            $customCSS = isset($email['css']) && !empty($email['css']) ? strip_tags($email['css']) : '';
    
            $cssInliner = CssInliner::fromHtml($body)->inlineCss($customCSS);

            $body = $cssInliner->render();
        }

        // Fix space characters displayed as ???? in old email clients like SquirrelMail.
        // Ticket reference: https://smilemotive.teamwork.com/desk/tickets/96313487/messages
        $specialSpace = [
            "\xC2\xA0",
            "\xE1\xA0\x8E",
            "\xE2\x80\x80",
            "\xE2\x80\x81",
            "\xE2\x80\x82",
            "\xE2\x80\x83",
            "\xE2\x80\x84",
            "\xE2\x80\x85",
            "\xE2\x80\x86",
            "\xE2\x80\x87",
            "\xE2\x80\x88",
            "\xE2\x80\x89",
            "\xE2\x80\x8A",
            "\xE2\x80\x8B",
            "\xE2\x80\xAF",
            "\xE2\x81\x9F",
            "\xEF\xBB\xBF",
        ];

        $body = str_replace($specialSpace, " ", $body);

        $mailer
            ->addRecipient($email['recipient'])
            ->isHTML(true)
            ->setSubject($email['subject'])
            ->setBody($body);

        // Prepare plain text alternative body
        $altBody = $body;
        
        // Convert <a href="...">text</a> to 'text (URL)'
        $altBody = preg_replace_callback(
            '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
            function ($matches) {
                $text = trim($matches[2]);
                $url = trim($matches[1]);
                return $text . ' (' . $url . ')';
            },
            $altBody
        );

        $altBody = preg_replace('/<style\\b[^>]*>(.*?)<\\/style>/is', '', $altBody);
        
        $altBody = strip_tags(str_ireplace(['<br />', '<br>', '<br/>'], "\r\n", $altBody));
        
        // Replace non-breaking spaces and similar unicode spaces with a normal space
        $altBody = preg_replace('/[\\xA0\\xC2\\xA0\\xE2\\x80\\x8A\\xE2\\x80\\x8B\\xE2\\x80\\xAF\\xE2\\x81\\x9F\\xEF\\xBB\\xBF]+/u', ' ', $altBody);
        
        // Remove leading/trailing spaces on each line
        $altBody = preg_replace('/^[ \\t]+|[ \\t]+$/m', '', $altBody);
        
        // Replace 3 or more consecutive newlines with just 2 (single blank line)
        $altBody = preg_replace("/(\r?\n){3,}/", "\n\n", $altBody);
        
        // Trim leading/trailing whitespace
        $altBody = trim($altBody);
        
        $mailer->AltBody = $altBody;

        // Add CC
        if (!empty($email['cc']))
        {
            $mailer->addCc($email['cc']);
        }

        // Add BCC
        if (!empty($email['bcc']))
        {
            $mailer->addBcc($email['bcc']);
        }

        // Attachments
        $attachments = $email['attachments'];

        if (!empty($attachments))
        {
            if (!is_array($attachments))
            {
                $attachments = explode(',', $attachments);
            }

            // Validate Attachments
            $attachments = array_filter(array_map('trim', $attachments));

            foreach ($attachments as $attachment)
            {
                $file_path = $this->toRelativePath($attachment);

                if (!is_file($file_path))
                {
                    continue;
                }

                $mailer->addAttachment($file_path);
            }
        }

        // Send mail
        $send = $mailer->Send();
        
        if ($send !== true)
        {
            $this->setError($send->__toString());
            return;
        }

        return true;
    }

    /**
     *  Set Class Error
     *
     *  @param  string   $error   The error message
     */
    private function setError($error)
    {
        $this->error = 'Error sending email: ' . $error;
    }

    /**
     *  Removes all illegal characters and validates an email address
     *
     *  @param   string  $email  Email address string
     *
     *  @return  bool
     */
    private function validateEmailAddress($email)
    {
		// If the email address contains an ampersand, throw an error
		if (strpos($email, '&') !== false)
		{
			return false;
		}

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Transform an attachment reference to a path relative to the site's root.
     *
     * A reference is a URL on this site, an absolute path inside it, a path already
     * relative to it, or an absolute path elsewhere. Only the last stays absolute, so a
     * file above the web root can still be attached.
     *
     * A leading slash does not separate the two: an in-site reference is stored
     * site-relative with one, and so is a path on another volume. Whichever reading
     * names a real file wins, site first.
     *
     * Containment is tested rather than string-replaced, so a sibling folder such as
     * /var/www/html-uploads next to a site at /var/www/html is not mangled.
     *
     * @param  string $url
     *
     * @return string
     */
    private function toRelativePath($url)
    {
        $url = trim((string) $url);

        if ($url === '')
        {
            return '';
        }

        $root = Uri::root();

        if ($root !== '' && strpos($url, $root) === 0)
        {
            return ltrim(Path::clean(substr($url, strlen($root))), '/\\');
        }

        foreach ([JPATH_SITE, JPATH_ROOT] as $base)
        {
            if (SafePath::isWithinLexical($url, $base))
            {
                $base = rtrim(Path::clean($base), DIRECTORY_SEPARATOR);

                return ltrim(substr(Path::clean($url), strlen($base)), '/\\');
            }
        }

        $path     = Path::clean($url);
        $relative = ltrim($path, '/\\');

        if (!SafePath::isAbsolute($path) || is_file(rtrim(Path::clean(JPATH_ROOT), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative))
        {
            return $relative;
        }

        return $path;
    }
}