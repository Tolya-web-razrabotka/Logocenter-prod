$(document).ready(function () {

	// Фиксированная шапка

	var $header = $('.header');
	function toggleHeader() {
		$header.toggleClass('headerFixed', $(window).scrollTop() > 10);
	}
	$(window).on('scroll', toggleHeader);
	toggleHeader();

	// Бургер-меню

	function openMenu() {
		$('body').addClass('menuOpen');
	}
	function closeMenu() {
		$('body').removeClass('menuOpen');
	}
	$('.headerBurger').on('click', openMenu);
	$('.mobileMenuClose, .mobileMenuOverlay, .mobileMenuNav a').on('click', closeMenu);
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			closeMenu();
			closeModal();
		}
	});

	// Модалка обратной связи

	var $modal = $('.callbackModal');
	var $lastToggle = null;

	function openModal($toggle) {
		$lastToggle = $toggle;
		closeMenu();
		$('body').addClass('modalOpen');
		$modal.attr('aria-hidden', 'false');
		setTimeout(function () {
			$modal.find('.requestFormInput, .cf-control-input > .cf-input').first().trigger('focus');
		}, 400);
	}
	function closeModal() {
		if (!$('body').hasClass('modalOpen')) return;
		$('body').removeClass('modalOpen');
		$modal.attr('aria-hidden', 'true');
		if ($lastToggle) $lastToggle.trigger('focus');
	}
	$(document).on('click', '.callback-toggle', function (e) {
		e.preventDefault();
		openModal($(this));
	});
	$('.callbackModalClose, .callbackModalOverlay').on('click', closeModal);


	// Формы Convert Forms: одна форма в нескольких блоках

	$('.requestForm .convertforms').each(function (i) {
		var $form = $(this);
		var source = $form.closest('.requestForm').data('source');

		if (source) $form.find('input[name="cf[source]"]').val(source);
		if (i === 0) return;

		// У второй и следующих копий делаем id уникальными, чтобы label не переключали чужие поля
		$form.find('[id]').each(function () {
			var oldId = this.id;
			var newId = oldId + '_' + i;
			this.id = newId;
			$form.find('[for="' + oldId + '"]').attr('for', newId);
		});
	});



	// Выпадающий список услуг

	$('.requestFormSelect, .requestForm .cf-select').each(function () {
		var $wrap = $(this).addClass('requestFormSelect isCustom');
		var $select = $wrap.find('select').attr('tabindex', '-1');
		var $btn = $('<button type="button" class="requestFormSelectBtn" aria-haspopup="listbox" aria-expanded="false"><span></span></button>');
		var $list = $('<ul class="requestFormSelectList" role="listbox"></ul>');

		$select.find('option').each(function (i) {
			if ($(this).is(':disabled')) return;
			$('<li class="requestFormSelectOption" role="option"></li>')
				.text($(this).text().trim())
				.attr('data-index', i)
				.appendTo($list);
		});
		$wrap.append($btn, $list);

		var $options = $list.children();

		function render() {
			var $current = $select.find('option:selected');
			$btn.find('span').text($current.text().trim());
			$btn.toggleClass('isPlaceholder', $current.is(':disabled'));
			$options.removeClass('isSelected').attr('aria-selected', 'false')
				.filter('[data-index="' + $current.index() + '"]').addClass('isSelected').attr('aria-selected', 'true');
		}
		function setActive($option) {
			$options.removeClass('isActive');
			$option.addClass('isActive');
		}
		function open() {
			$('.requestFormSelect.isOpen').not($wrap).each(function () {
				$(this).removeClass('isOpen').find('.requestFormSelectBtn').attr('aria-expanded', 'false');
			});
			$wrap.addClass('isOpen');
			$btn.attr('aria-expanded', 'true');
			var $selected = $options.filter('.isSelected');
			setActive($selected.length ? $selected : $options.first());
		}
		function close() {
			$wrap.removeClass('isOpen');
			$btn.attr('aria-expanded', 'false');
		}
		function choose($option) {
			$select.prop('selectedIndex', $option.data('index')).trigger('change');
			render();
			close();
			$btn.trigger('focus');
		}

		$select.on('invalid', function () {
			$wrap.addClass('isError');
		}).on('change', function () {
			$wrap.removeClass('isError');
		});
		$select.closest('form').on('reset', function () {
			setTimeout(function () {
				$wrap.removeClass('isError');
				render();
			});
		});


		$btn.on('click', function () {
			$wrap.hasClass('isOpen') ? close() : open();
		});
		$options.on('click', function () {
			choose($(this));
		}).on('mouseenter', function () {
			setActive($(this));
		});
		$wrap.on('keydown', function (e) {
			var isOpen = $wrap.hasClass('isOpen');
			var $active = $options.filter('.isActive');
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (!isOpen) return open();
				var $next = e.key === 'ArrowDown' ? $active.next() : $active.prev();
				if ($next.length) setActive($next);
			} else if ((e.key === 'Enter' || e.key === ' ') && isOpen) {
				e.preventDefault();
				if ($active.length) choose($active);
			} else if (e.key === 'Escape' && isOpen) {
				e.stopPropagation();
				close();
				$btn.trigger('focus');
			} else if (e.key === 'Tab') {
				close();
			}
		});
		$(document).on('click', function (e) {
			if (!$wrap.is(e.target) && !$wrap.has(e.target).length) close();
		});

		render();
	});
	$(window).on('resize', function () {
		if ($(window).width() > 1279) closeMenu();
	});

	// Слайдеры техники и материалов

	var sliderMaxSlides = 4;

	$('.cardsSliderWrap').each(function () {
		var $wrap = $(this);
		var $slider = $wrap.find('.cardsSlider');
		var $items = $slider.children('.cardsSliderItem');

		// Пока карточек не больше, чем помещается на экран, slick не листает —
		// дублируем набор, чтобы карусель крутилась по кругу
		if ($items.length && $items.length <= sliderMaxSlides) {
			$items.clone().addClass('cardsSliderItemClone').appendTo($slider);
		}

		$slider.slick({
			slidesToShow: sliderMaxSlides,
			slidesToScroll: 1,
			infinite: true,
			speed: 400,
			arrows: false,
			draggable: true,
			swipe: true,
			swipeToSlide: true,
			touchThreshold: 8,
			responsive: [
				{
					breakpoint: 1200,
					settings: {
						slidesToShow: 3
					}
				},
				{
					breakpoint: 992,
					settings: {
						slidesToShow: 2
					}
				},
				{
					breakpoint: 601,
					settings: {
						slidesToShow: 1
					}
				}
			]
		});

		$wrap.find('.cardsSliderPrev').on('click', function () {
			$slider.slick('slickPrev');
		});
		$wrap.find('.cardsSliderNext').on('click', function () {
			$slider.slick('slickNext');
		});
	});

	
	// Яндекс карта на странице «Контакты»

	var $maps = $("#map");

	if ($maps.length > 0) {
		var center = [56.34123306838814, 43.92087099999998];

		function createMap(mapId) {
			var map = new ymaps.Map(mapId, {
				center: center,
				zoom: 12,
			});

			var placemark = new ymaps.Placemark(
				center,
				{},
				{
					iconLayout: "default#image",
					iconImageHref: "/media/templates/site/logocenter/images/svg/marker.svg",
					iconImageSize: [40, 40],
					iconImageOffset: [-19, -44],
				}
			);

			placemark.events.add("click", function () {
				var url = "https://yandex.ru/maps/-/CXqE4Z0y";
				window.open(url, "_blank");
			});

			map.controls.remove("geolocationControl");
			map.controls.remove("searchControl");
			map.controls.remove("trafficControl");
			map.controls.remove("typeSelector");
			map.controls.remove("fullscreenControl");
			map.controls.remove("zoomControl");
			map.controls.remove("rulerControl");

			map.geoObjects.add(placemark);
		}

		ymaps.ready(function () {
			createMap("map");
		});
	}

	
	// Галерея на странице товара

	$('.productGallery').each(function () {
		var $gallery = $(this);
		var $slider = $gallery.find('.productGallerySlider');

		$slider.slick({
			slidesToShow: 2,
			slidesToScroll: 1,
			infinite: true,
			speed: 400,
			arrows: false,
			swipeToSlide: true,
			touchThreshold: 8,
			responsive: [
				{
					breakpoint: 768,
					settings: {
						slidesToShow: 1
					}
				}
			]
		});

		$gallery.find('.cardsSliderPrev').on('click', function () {
			$slider.slick('slickPrev');
		});
		$gallery.find('.cardsSliderNext').on('click', function () {
			$slider.slick('slickNext');
		});
	});
});
