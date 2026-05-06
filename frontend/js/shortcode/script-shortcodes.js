// COUNT UP
jQuery(document).ready(function ($) {
  "use strict";
  var datafile = $('.counter').data('file');
  $('.counter').counterUp({
    delay: 10,
    time: datafile
  });
});
// END COUNT UP


jQuery(document).ready(function ($) {
  "use strict";
  $('.circular-item-style-1').appear();
  $(document.body).on('appear', '.circular-item-style-1', function () {

    // Radial progress bar
    var easy_pie_chart = {};
    $('.circular-item-style-1').removeClass("hidden");
    $('.circular-pie-style-1').each(function () {
      $(this).easyPieChart($.extend(true, {}, easy_pie_chart, {
        size: 100,
        animate: 2000,
        lineCap: 'square',
        barColor: $(this).data('color'),
        lineWidth: 4,
        scaleColor: false,
        trackColor: '#DAD9DB',


        onStep: function (from, to, percent) {
          this.el.children[0].innerHTML = Math.round(percent) + '%';
        }
      }));
    });
  });
});


jQuery(document).ready(function ($) {
  "use strict";
  $('.circular-item-style-team').appear();
  $(document.body).on('appear', '.circular-item-style-team', function () {

    // Radial progress bar
    var easy_pie_chart = {};
    $('.circular-item-style-team').removeClass("hidden");
    $('.circular-pie-style-team').each(function () {
      $(this).easyPieChart($.extend(true, {}, easy_pie_chart, {

        animate: 2000,
        lineWidth: 2,
        lineCap: 'square',
        barColor: $(this).data('color'),
        scaleColor: false,
        trackColor: '#F3F3F3',


        onStep: function (from, to, percent) {
          this.el.children[0].innerHTML = Math.round(percent) + '%';
        }
      }));
    });
  });
});

jQuery(document).ready(function ($) {
  "use strict";
  $('.circular-item-style-2').appear();
  $(document.body).on('appear', '.circular-item-style-2', function () {

    // Radial progress bar
    var easy_pie_chart = {};
    $('.circular-item-style-2').removeClass("hidden");
    $('.circular-pie-style-2').each(function () {
      $(this).easyPieChart($.extend(true, {}, easy_pie_chart, {
        size: 220,
        animate: 2000,
        lineWidth: 42,
        lineCap: 'square',
        barColor: $(this).data('color'),
        scaleColor: false,
        trackColor: 'transparent',
        onStep: function (from, to, percent) {
          this.el.children[0].innerHTML = Math.round(percent) + '%';
        }
      }));
    });
  });
});


jQuery(document).ready(function ($) {
  "use strict";
  $('.circular-item-style-3').appear();
  $(document.body).on('appear', '.circular-item-style-3', function () {

    // Radial progress bar
    var easy_pie_chart = {};
    $('.circular-item-style-3').removeClass("hidden");
    $('.circular-pie-style-3').each(function () {
      $(this).easyPieChart($.extend(true, {}, easy_pie_chart, {
        size: 220,
        animate: 2000,
        lineWidth: 42,
        lineCap: 'square',
        barColor: $(this).data('color'),
        scaleColor: false,
        trackColor: '#DAD9DB',
        onStep: function (from, to, percent) {
          this.el.children[0].innerHTML = Math.round(percent) + '%';
        }
      }));
    });
  });
});

jQuery(document).ready(function () {
  $window = jQuery(window);

  jQuery('.parallax').each(function () {
    var $scroll = $(this);

    jQuery(window).on('scroll', function () {
      var yPos = -($window.scrollTop() / $scroll.data('speed'));
      var coords = '50% ' + yPos + 'px';
      $scroll.css({backgroundPosition: coords});
    });
  });
});



