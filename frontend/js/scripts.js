jQuery(document).ready(function ($) {
  "use strict";
  $(document).foundation();
  var isMobile = {
    Android: function () {
      return navigator.userAgent.match(/Android/i);
    },
    BlackBerry: function () {
      return navigator.userAgent.match(/BlackBerry/i);
    },
    iOS: function () {
      return navigator.userAgent.match(/iPhone|iPad|iPod/i);
    },
    Opera: function () {
      return navigator.userAgent.match(/Opera Mini/i);
    },
    Windows: function () {
      return navigator.userAgent.match(/IEMobile/i);
    },
    any: function () {
      return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
    }
  };


  $(".creative-layout .search-section i").on('click', function () {
    var search_icon = $(this);
    if ($("#s").hasClass('hide')) {
      $("#s").removeClass('hide');
      search_icon.removeClass('fa-search');
      search_icon.addClass('fa-close');
    } else {
      $("#s").addClass('hide');
      search_icon.removeClass('fa-close');
      search_icon.addClass('fa-search');
    }
    return false;
  });


  if (!isMobile.any()) {

    /*_________________________________ Waypoints ___________________*/
    var waypoints = $('.main').waypoint({
      handler: function (direction) {
        $('.wd-section-blog-services.style-3').addClass('anim-on');
        $('.wd-section-blog-services.style-3 .wd-blog-post').addClass('nohover');
      },
      offset: '45%'
    });

    $('.animated').css('opacity', 0);


    //___________ Add animation delay
    var thisParent = $(this).closest('.animation-parent'),
      animationDelay = thisParent.data('animation-delay');

    // find ".animation-parent"
    $('.animation-parent').each(function (index, element) {
      // find each ".animated" in the current ".animation-parent"
      $('.animated', $(this)).each(function (index, element) {
        thisParent = $(this).closest('.animation-parent');
        animationDelay = thisParent.data('animation-delay');
        animationDelay = animationDelay * index;
        $(this).css('animation-delay', animationDelay + 'ms');
      });
    });


    //___________ animate Element when it visible
    $('.animated').waypoint(function () {
        $(this).css('opacity', 1);
        $(this).addClass($(this).data('animated'));
      },
      {offset: 'bottom-in-view'});


  } else {
    $("div").removeClass('wpb_animate_when_almost_visible');
    $('.animated').css('opacity', 1);
  }


  $(document).foundation({
    equalizer: {
      // Specify if Equalizer should make elements equal height once they become stacked.
      equalize_on_stack: true
    }
  });


  $('video,audio').mediaelementplayer();

  $(".image").on('click', function (e) {
    var url_image = '.' + $(this).data('url');
    $('.wd-all-image > div').addClass('wd-hide');
    $(url_image).removeClass('wd-hide');
    $(".image").removeClass('active');
    $(this).addClass('active');
  });


  /*************************** Configuration Panel ****************************************/
  var movementsize = -160;
  $(".styleswitcher-contener .selector").on("click", function () {
    $('.styleswitcher').animate({
        left: '+=' + movementsize
      }, 500, function () {
        movementsize = movementsize * -1;
      }
    );
  });

  $('.styleswitcher').animate({left: '+=' + movementsize}, 1500, function () {
      movementsize = movementsize * -1;
    }
  );

  $('input[type=radio][name=switch-color]').change(
    function () {
      if (this.value == 'white') {
        $('body').removeClass('black');
      }
      else if (this.value == 'black') {
        $('body').addClass('black');
      }
    }
  );
  $('input[type=radio][name=switch-layout]').change(
    function () {
      if (this.value == 'full') {
        $('body').removeClass('l-boxed');
      }
      else if (this.value == 'boxed') {
        $('body').addClass('l-boxed');
      }
    }
  );

  /*
   * ---------blog bare height-------------
   */


  function blog_info_height() {
    $(".blog-info").each(function () {
      $(this).css('height', $(this).parent().height());
    });
  }

  blog_info_height();

  /*************************** Animation ****************************************/
  $('.animated-content').appear(function () {
    $(this).addClass('animated');
  }, {accX: 50, accY: 100});


  /*************************** Lists ****************************************/
  var classList;
  var sectionclass;
  $(".list-icon li").each(function (index) {
    classList = $(this).parent().attr('class').split(/\s+/);
    var iconclass = classList[1].replace('list-', '');

    $(this).prepend('<i class="fa ' + iconclass + '"></i>');
  });


  $(".show-cart-btn").hoverIntent({
    over: cartover,
    out: cartout,
    timeout: 500
  });

  function cartover() {
    $('.hidden-cart')
      .stop(true, true)
      .fadeIn({duration: 500, queue: false})
      .css('display', 'none')
      .slideDown(500);
  }

  function cartout() {
    $('.hidden-cart')
      .stop(true, true)
      .fadeOut({duration: 100, queue: false})
      .slideUp(100);
  }


  $("hidden-cart").on('mouseover', function () {
    $(this).css("display", "block");
  });

  $("hidden-cart").mouseout(function () {
    $(this).css("display", "none");
  });


  // Full Screen
  $('.wd_full_screen').css({'height': ($(window).height()) + 'px'});

  $(window).resize(function () {

    blog_info_height();

    /////////////////// centered tabs ///////////////////////////////////////
    var section_containers = $('.section-container.auto.center');
    $.each(section_containers, function (key, section_container) {
      section_container = $(section_container);
      // convert section_container to jquery object
      var section_containerWidth = section_container.width(), titles = section_container.find('p.title'), titleWidth = titles.first().width(), titleLen = titles.length, titleWidth = titles.first().width();

      $.each(titles, function (key2, value2) {
        $(value2).animate({
          'left': ((section_containerWidth / 2) - ((titleWidth * titleLen ) / 2) + key2 * titleWidth),
        }, 100, 'swing');
      });
    });

    /*___________________ Full Screen __________________________*/

    if ($(".wd_full_screen").length) {
      $('.wd_full_screen').css({'height': ($(window).height()) + 'px'});
    }

  });


  // ---------------modern Menu effect-----------------
  $("#trigger-overlay").on("click", function () {
    $('html').css('overflow', 'hidden');
    $('.overlay').addClass('open');
  });
  $(".overlay-close").on("click", function () {
    $('html').css('overflow', 'scroll');
    $('.overlay').removeClass('open');
  });
// --------------------------------------------------


  /* World Map Triggers to Popup */

  var offices_location = jQuery('.offices-locations'),
    offices_list = jQuery('.offices-list'),
    office_location_point = offices_location.children('.office-location-point'),
    offices_list_name = offices_list.find('.location-name'),
    clicked;

  office_location_point.each(function (index, el) {
    var $el = jQuery(el);
    $el.css({
      top: parseInt($el.attr('data-positiontop'), 10),
      left: parseInt($el.attr('data-positionleft'), 10)
    })
      .on('mouseover', function () {
        offices_list.find("[data-location='" + $el.attr('data-location') + "']").addClass('selected');
      })
      .on('mouseout', function () {
        offices_list_name.removeClass('selected');
      });
    ;
  });

  offices_list_name.on('mouseover', function () {
    offices_location.find("[data-location='" + jQuery(this).attr('data-location') + "']").addClass('selected');
  })
    .on('mouseout', function () {
      office_location_point.removeClass('selected');
    });

  // -------------------------------------------------------------
  //   sly carousel
  // -------------------------------------------------------------
  (function () {
    var $frame = jQuery('.wd-sly-carousel');
    var $slidee = $frame.children('ul').eq(0);
    var $wrap = $frame.parent();

    // Call Sly on frame
    $frame.sly({
      horizontal: 1,
      itemNav: 'basic',
      smart: 1,
      activateOn: 'click',
      mouseDragging: 1,
      touchDragging: 1,
      releaseSwing: 1,
      startAt: 3,
      scrollBar: $wrap.find('.scrollbar'),
      scrollBy: 1,
      pagesBar: $wrap.find('.pages'),
      activatePageOn: 'click',
      speed: 300,
      elasticBounds: 1,
      easing: 'easeOutExpo',
      dragHandle: 1,
      dynamicHandle: 1,
      clickBar: 1,

    });

  }());


  //////////////////  Spacer //
  if ($('.wd_empty_space').length) {

    $('.wd_empty_space').each(function (i, obj) {
      wd_empty_space_padding(this);
    });

    window.addEventListener('resize', function () {
      $('.wd_empty_space').each(function (i, obj) {
        wd_empty_space_padding(this);
      });
    }, true);
  }

  function wd_empty_space_padding(el) {
    var $mobile_height = $(el).data("heightmobile"),
      $tablet_height = $(el).data("heighttablet"),
      $desktop_height = $(el).data("heightdesktop");

    if (Modernizr.mq("(max-width: 40em)")) {
      $(el).css("height", $mobile_height);
    } else if (Modernizr.mq("(min-width: 40.063em) and (max-width: 64em)")) {
      $(el).css("height", $tablet_height);
    } else if (Modernizr.mq("(min-width: 64.063em)")) {
      $(el).css("height", $desktop_height);
    }
    $(document).foundation('equalizer', 'reflow');
  }

  //////////////// Delimiter /////
  if ($('.row-delimiter').length) {

    $('.row-delimiter').each(function (i, obj) {
      wd_delimiter_transform(this);
    });

    window.addEventListener('resize', function () {
      $('.row-delimiter').each(function (i, obj) {
        wd_delimiter_transform(this);
      });
    }, true);
  }

  function wd_delimiter_transform(el) {
    var left = '920';
    if ($(el).hasClass('vertical_line_bottom_left')) {
      left = parseInt($(el).parent().css('width'), 10) / 2;
    } else if ($(el).hasClass('vertical_line_bottom_right')) {
      left = parseInt($(el).parent().css('width'), 10) + parseInt($(el).parent().css('left'), 10);
    } else {
      left = parseInt($(el).parent().css('left'), 10) * -1;
    }

    $(el).css('transform', 'translateY(100%) translateX(' + left + 'px)');
  }


  var $testimonial_quote_color = $(".testimonial-text").data('quotecolor');
  var $testimonial_quote_opacity = $(".testimonial-text").data('quoteopacity');
  var $testimonial_quote_size = $(".testimonial-text").data('quotesize');


  $('head').append('<style> .wd-testimonail .testimonial-text:before, .wd-testimonail .testimonial-text:after {color:' + $testimonial_quote_color + ';opacity:.' + $testimonial_quote_opacity + ';font-size:' + $testimonial_quote_size + 'px;}</style>');

//___________ Portfolio Grid Isotope


  window.onload = function () {

    if ($('.wd-portfolio-grid').length) {
      $('.wd-portfolio-grid').each(function (i, obj) {
        portfolio_grid_setting(this);
      });
    }

    function portfolio_grid_setting(el) {
      var $voip_portfolio_grid = $(el).isotope({
        itemSelector: '.wd-portfolio-grid-item',
        layoutMode: 'fitRows'
      })

      $('.filters').on('click', 'a', function (e) {
        e.preventDefault();
        var filterValue = $(this).attr('data-filter');
        $(".filters a").removeClass('current');
        $(this).addClass('current');
        $voip_portfolio_grid.isotope({filter: filterValue});
      });
    }


    if ($('.wd-portfolio-masonry').length) {
      $('.wd-portfolio-masonry').each(function (i, obj) {
        portfolio_masonry_setting(this);
      });
    }

    function portfolio_masonry_setting(el) {

      var $voip_portfolio_masonry = $(el).isotope({
        itemSelector: '.wd-portfolio-masonry-item'
      })

      $('.filters').on('click', 'a', function (e) {
        e.preventDefault();
        var filterValue = $(this).attr('data-filter');
        $(".filters a").removeClass('current');
        $(this).addClass('current');
        $voip_portfolio_masonry.isotope({filter: filterValue});
      });
    }


    if ($('.wd-portfolio-masonry-free-style.style-1').length) {
      $('.wd-portfolio-masonry-free-style.style-1').each(function (i, obj) {
        portfolio_masonry_free_style_1_setting(this);
      });
    }

    function portfolio_masonry_free_style_1_setting(el) {

      var $voip_portfolio_masonry = $(el).isotope({
        itemSelector: '.wd-portfolio-masonry-item'
      })

      $('.filters').on('click', 'a', function (e) {
        e.preventDefault();
        var filterValue = $(this).attr('data-filter');
        $(".filters a").removeClass('current');
        $(this).addClass('current');
        $voip_portfolio_masonry.isotope({filter: filterValue});
      });
    }


    if ($('.wd-portfolio-masonry-free-style.style-2').length) {
      $('.wd-portfolio-masonry-free-style.style-2').isotope('destroy');
      $('.wd-portfolio-masonry-free-style.style-2').each(function (i, obj) {
        portfolio_masonry_free_style_2_setting(this);
      });
    }

    function portfolio_masonry_free_style_2_setting(el) {
      var $container = $(el);
      var $containerProxy = $container.clone().empty().css({visibility: 'hidden'});
      var colWidth;

      $container.after($containerProxy);

      $(window).resize(function () {
        colWidth = Math.floor($containerProxy.width() / 4);
        $container.css({
          width: colWidth * 4
        })
        $container.isotope({
          resizable: false,
          itemSelector: '.wd-portfolio-masonry-item',
          masonry: {
            columnWidth: colWidth
          }
        });
      }).resize();


      $(window).load(function () {
        $container.isotope('layout');
      });

      var filtertoggle = jQuery('body');

      $(window).load(function () {
        $container.isotope('layout');
        $(function () {
          setTimeout(function () {
            $container.isotope('layout');
          }, filtertoggle.hasClass("") ? 755 : 755);
        });
      });


      $(window).resize(function () {
        $container.isotope('layout');
        $(function () {
          setTimeout(function () {
            $container.isotope('layout');
          }, filtertoggle.hasClass("") ? 755 : 755);
        });
      });


      $('.filters').on('click', 'a', function (e) {
        e.preventDefault();
        var filterValue = $(this).attr('data-filter');
        $(".filters a").removeClass('current');
        $(this).addClass('current');
        $voip_portfolio_masonry.isotope({filter: filterValue});
      });
    }


    //****************** rtl ******************
    function _rtl() {
      if (jQuery('html').attr('dir') == 'rtl') {
        jQuery('[data-vc-full-width="true"]').each(function (i, v) {
          jQuery(this).css('right', jQuery(this).css('left')).css('left', 'auto');
        });
      }
    }

    jQuery(document).on('vc-full-width-row', function () {
      _rtl();
    });
    _rtl();


    /*
     var $voip_portfolio_grid = $('.wd-portfolio-grid').isotope();
     var $voip_portfolio_masonry = $('.wd-portfolio-masonry').isotope();*/

  };
});