<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace ConvertForms\Tasks;

defined('_JEXEC') or die('Restricted access');

/**
 * Helper class to encrypt and decrypt connection API keys for export/import
 */
class ConnectionEncryption
{
    /**
     * Fixed salt used for encryption/decryption.
     * 
     * @var string
     */
    private const SALT = 'CF_c0nn3ct10n_s4lt';

    /**
     * The encryption method to use
     * 
     * @var string
     */
    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Known API key field names in connection params that should be encrypted.
     * 
     * When adding new connection types with sensitive credentials (API keys, tokens, hashes, secrets),
     * add the parameter field name to this array to ensure it's encrypted during export/import.
     * 
     * @var array
     */
    private const API_KEY_FIELDS = ['api_key', 'api_app_id', 'org_id'];

    /**
     * Encrypt a string value
     *
     * @param   string  $value  The value to encrypt
     *
     * @return  string|null  The encrypted value or null if encryption failed
     */
    public static function encrypt($value)
    {
        if (empty($value))
        {
            return $value;
        }

        // Generate a random IV (Initialization Vector)
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Derive key from salt
        $key = hash('sha256', self::SALT, true);

        // Encrypt the value
        $encrypted = openssl_encrypt($value, self::CIPHER_METHOD, $key, 0, $iv);

        if ($encrypted === false)
        {
            return null;
        }

        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string value
     *
     * @param   string  $encrypted  The encrypted value
     *
     * @return  string|null  The decrypted value or null if decryption failed
     */
    public static function decrypt($encrypted)
    {
        if (empty($encrypted))
        {
            return $encrypted;
        }

        // Decode the base64 encoded string
        $data = base64_decode($encrypted, true);

        if ($data === false)
        {
            return null;
        }

        // Extract IV
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $ivLength);
        $encryptedValue = substr($data, $ivLength);

        // Derive key from salt
        $key = hash('sha256', self::SALT, true);

        // Decrypt the value
        $decrypted = openssl_decrypt($encryptedValue, self::CIPHER_METHOD, $key, 0, $iv);

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Encrypt API keys in connection params
     *
     * @param   array  $params  The connection params
     *
     * @return  array  The params with encrypted API keys
     */
    public static function encryptConnectionParams($params)
    {
        if (!is_array($params))
        {
            return $params;
        }

        foreach (self::API_KEY_FIELDS as $field)
        {
            if (!isset($params[$field]) || empty($params[$field]))
            {
                continue;
            }
            
            $encrypted = self::encrypt($params[$field]);
            if ($encrypted !== null)
            {
                $params[$field] = $encrypted;
            }
        }

        return $params;
    }

    /**
     * Decrypt API keys in connection params
     *
     * @param   array  $params  The connection params with encrypted keys
     *
     * @return  array  The params with decrypted API keys
     */
    public static function decryptConnectionParams($params)
    {
        if (!is_array($params))
        {
            return $params;
        }

        foreach (self::API_KEY_FIELDS as $field)
        {
            if (!isset($params[$field]) || empty($params[$field]))
            {
                continue;
            }
            
            $decrypted = self::decrypt($params[$field]);
            if ($decrypted !== null)
            {
                $params[$field] = $decrypted;
            }
        }

        return $params;
    }

    /**
     * Extract API key from connection params for comparison
     * Returns the first found API key field value
     *
     * @param   array  $params  The connection params
     *
     * @return  string|null  The API key value or null if not found
     */
    public static function extractApiKey($params)
    {
        if (!is_array($params))
        {
            return null;
        }

        foreach (self::API_KEY_FIELDS as $field)
        {
            if (!isset($params[$field]) || empty($params[$field]))
            {
                continue;
            }
            
            return $params[$field];
        }

        return null;
    }
}
