jQuery(document).ready(function ($) {
  "use strict";
  /**  ------------ Maps -------------------
   **/

  function initializeMap() {

    var styles = [{
      "featureType": "administrative",
      "elementType": "all",
      "stylers": [{
        "visibility": "on"
      }, {
        "saturation": -100
      }, {
        "lightness": 20
      }]
    }, {
      "featureType": "road",
      "elementType": "all",
      "stylers": [{
        "visibility": "on"
      }, {
        "saturation": -100
      }, {
        "lightness": 40
      }]
    }, {
      "featureType": "water",
      "elementType": "all",
      "stylers": [{
        "visibility": "on"
      }, {
        "saturation": -10
      }, {
        "lightness": 30
      }]
    }, {
      "featureType": "landscape.man_made",
      "elementType": "all",
      "stylers": [{
        "visibility": "simplified"
      }, {
        "saturation": -60
      }, {
        "lightness": 10
      }]
    }, {
      "featureType": "landscape.natural",
      "elementType": "all",
      "stylers": [{
        "visibility": "simplified"
      }, {
        "saturation": -60
      }, {
        "lightness": 60
      }]
    }, {
      "featureType": "poi",
      "elementType": "all",
      "stylers": [{
        "visibility": "off"
      }, {
        "saturation": -100
      }, {
        "lightness": 60
      }]
    }, {
      "featureType": "transit",
      "elementType": "all",
      "stylers": [{
        "visibility": "off"
      }, {
        "saturation": -100
      }, {
        "lightness": 60
      }]
    }];

    var styledMap = new google.maps.StyledMapType(styles, {
      name: "Styled Map"
    });

    var mapOptions = {
      scaleControl: true,
      scrollwheel: false,
      center: new google.maps.LatLng(latitude, longitude),
      zoom: wd_zoom,
      mapTypeControlOptions: {
        mapTypeIds: [google.maps.MapTypeId.ROADMAP, 'map_style']
      }

    };

    var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
    //Associate the styled map with the MapTypeId and set it to display.
    map.mapTypes.set('map_style', styledMap);
    map.setMapTypeId('map_style');
    var image = window.location.origin + window.location.pathname + 'wp-content/themes/voip/images/marker_icon.png';
    var marker = new google.maps.Marker({
      map: map,
      icon: image,
      position: map.getCenter()
    });

    var infowindow = new google.maps.InfoWindow();
    if (companyname != "") {
      companyname = "<h4>" + companyname + "</h4>";
    }
    infowindow.setContent("<div class='map-description'>" + companyname + " " + description + "</div>");

    infowindow.open(map, marker);

    google.maps.event.addListener(marker, 'click', function () {
      infowindow.open(map, marker);
    });
  }

  if ($('#map-canvas').length > 0) {

    var latitude = $('#map-canvas').data('latitude'),
      longitude = $('#map-canvas').data('longitude'),
      wd_zoom = $('#map-canvas').data('zoom'),
      companyname = $('#map-canvas').data('companyname'),
      imagepath = $('#map-canvas').data('imagepath'),
      description = $('#map-canvas').data('decription');
    if (imagepath == "") {
      var image_markup = '';
    } else {
      var image_markup = '<div class="map-img"><img src="' + imagepath + '" alt="" width="320px"></div>';
    }


    google.maps.event.addDomListener(window, 'load', initializeMap);
  }
  /*---------------caro--------------*/

  var $direction;
  if ($('html').attr('dir') == 'rtl') {
    $direction = true;
  } else {
    $direction = false;
  }
  $('.carousel').owlCarousel({
    items: 1,
    rtl: $direction,
    margin: 10,
    autoplay: true,
    autoplayTimeout: 5000,
  });

  var $Bitmnumber = $(".carousel_blog").data("numberitem");
  var $Bmargin = $(".carousel_blog").data("margin");

  $('.carousel_blog').owlCarousel({
    items: $Bitmnumber,
    margin: $Bmargin,
    rtl: $direction,
    navText: ["<i class='fa fa-chevron-left'></i>", "<i class='fa fa-chevron-right'></i>"],
    autoplay: true,
    autoplayTimeout: 5000,
    responsiveClass: true,
    responsive: {
      0: {
        items: 1,
        nav: true,
        rtl: $direction,
      },
      600: {
        items: 2,
        rtl: $direction,
        nav: false
      },
      1000: {
        items: $Bitmnumber,
        nav: true,
        rtl: $direction,
        loop: false,
      }
    }
  });

  var $Pitmnumber = $(".carousel_portfolio").data("numberitem");
  var $Pmargin = $(".carousel_portfolio").data("margin");

  $('.carousel_portfolio').owlCarousel({
    items: $Pitmnumber,
    margin: $Pmargin,
    rtl: $direction,
    navText: ["<i class='fa fa-chevron-left'></i>", "<i class='fa fa-chevron-right'></i>"],
    autoplay: true,
    autoplayTimeout: 5000,
    responsiveClass: true,
    responsive: {
      0: {
        items: 1,
        nav: true,
        rtl: $direction,
      },
      600: {
        items: 2,
        rtl: $direction,
        nav: true
      },
      1000: {
        items: $Pitmnumber,
        rtl: $direction,
        nav: true,
        loop: true,
      }
    }
  });
  var show = $(".testimonials-box").data("show");
  $('.testimonials').owlCarousel({
    items: show,
    rtl: $direction,
    pagination: true,
  });
  var client_show = $(".carousel_client").data("clienttoshow");
  $('.carousel_client').owlCarousel({
    items: client_show,
    nav: true,
    rtl: $direction,
    margin: 10,
    navText: ["<i class='fa fa-chevron-left'></i>", "<i class='fa fa-chevron-right'></i>"],
    autoplay: true,
    autoplayTimeout: 5000,
    responsiveClass: true,
    responsive: {
      0: {
        items: 1,
        rtl: $direction,
        nav: true,
      },
      600: {
        items: 2,
        rtl: $direction,
        nav: false
      },
      1000: {
        items: client_show,
        rtl: $direction,
        nav: true,
        loop: true,
      }
    }
  });

  $('.wd-gallery-images-holder').owlCarousel({
    items: 1,
    nav: true,
    rtl: $direction,
    margin: 10,
    navText: ["<i class='fa fa-chevron-left'></i>", "<i class='fa fa-chevron-right'></i>"],
    autoplay: true,
    autoplayTimeout: 5000,
  });
  $('.shop-carousel').owlCarousel({
    items: 1,
    nav: true,
    rtl: $direction,
    margin: 10,
    dots: true,
    navText: ["<i class='fa fa-angle-left'></i>", "<i class='fa fa-angle-right'></i>"],
    autoplay: true,
    autoplayTimeout: 5000,
  });

  // Clients Shortcode
  var navigation_style_slider = $('.team-member-slider').data('navigation');
  var navigation_style_carousel = $('.team-member-carousel').data('navigation');

  var elements_to_show_mobile = $('.team-member-carousel').data('showmobile');
  var elements_to_show_tablet = $('.team-member-carousel').data('showtablet');
  var elements_to_show_desktop = $('.team-member-carousel').data('showdesktop');

  var elements_to_swipe = $('.team-member-carousel').data('swipe');
  if (navigation_style_slider == "dotts") {
    $(window).on('load', function () {

      $('.team-member-slider').owlCarousel({
        loop: true,
        margin: 0,
        nav: false,
        dots: true,
        items: 1,
        onInitialize: function (event) {
          if ($('.owl-carousel .item').length <= 1) {
            this.settings.loop = false;
          }
        }
      });
    });

  }
  if (navigation_style_slider == "arrows") {
    $(window).on('load', function () {
      $('.team-member-slider').owlCarousel({
        loop: true,
        margin: 0,
        nav: true,
        dots: false,
        items: 1,
        onInitialize: function (event) {
          if ($('.owl-carousel .item').length <= 1) {
            this.settings.loop = false;
          }
        }
      });
    });
  }
  if (navigation_style_carousel == "arrows") {
    $(window).on('load', function () {
      // Team member Carousel
      $('.team-member-carousel').owlCarousel({
        loop: true,
        margin: 0,
        nav: true,
        dots: false,
        responsive: {
          0: {
            items: elements_to_show_mobile
          },
          600: {
            items: elements_to_show_tablet
          },
          1000: {
            items: elements_to_show_desktop
          }
        },
        slideBy: elements_to_swipe,
        onInitialize: function (event) {
          if ($('.owl-carousel .item').length <= 1) {
            this.settings.loop = false;
          }
        }
      });
    });
  }

  if (navigation_style_carousel == "dotts") {
    $(window).on('load', function () {
      // Team member Carousel
      $('.team-member-carousel').owlCarousel({
        loop: true,
        margin: 0,
        nav: false,
        dots: true,
        slideBy: elements_to_swipe,
        esponsive: {
          0: {
            items: elements_to_show_mobile
          },
          600: {
            items: elements_to_show_tablet
          },
          1000: {
            items: elements_to_show_desktop
          }
        },
        onInitialize: function (event) {
          if ($('.owl-carousel .item').length <= 1) {
            this.settings.loop = false;
          }
        }
      });
    });

  }

  // _______________Testimonial
  if ($('.owl-testimonail').length) {
    $('.owl-testimonail').each(function (i, obj) {
      testimonial_slider_setting(this);
    });
  }


  function testimonial_slider_setting(el) {
    var $data = $(el).data('infinity');
    var owl_testimonial = $(el).owlCarousel({
      items: 1,
      nav: false,
      rtl: $direction,
      margin: 10,
      autoplay: true,
      loop: $data,
      navText: [""],
      autoplayTimeout: 5000,
      thumbs: true,
      thumbsPrerendered: true,
      responsiveClass: true,
      onInitialize: function (event) {
        if ($(el).find('.wd-testimonail-item').length <= 1) {
          this.settings.loop = false;
        }
      }
    });
    // Custom Navigation Events
    $(".testimonial-next").on('click', function () {
      owl_testimonial.trigger('next.owl.carousel');
    });
    $(".testimonial-prev").on('click', function () {
      owl_testimonial.trigger('prev.owl.carousel');
    });
  }

});