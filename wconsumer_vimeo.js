
(function ($) {
    Drupal.behaviors.wconsumer_vimeoEditForm = {
        attach: function (context, settings) {
            $('.wconsumer-vimeo-widget-upload-type', context).live('click', function() {
                $('.wconsumer-vimeo-widget-form').hide();
                $(this).next().slideDown('slow');
                
                var instance = $(this);
                $('.wconsumer-vimeo-widget-upload-form input[type="hidden"]', context).each(function() {
                    var name = $(this).attr('name');
                    if (name && name.indexOf('[upload_type]') >= 0)
                        $(this).val(instance.attr('data-type'));
                });
            });
            
            $('.wconsumer-vimeo-widget .wconsumer-vimeo-widget-existing-videos ul li').live('click', function() {
                $('.wconsumer-vimeo-widget .wconsumer-vimeo-widget-existing-videos ul li').removeClass('selected');
                $(this).addClass('selected');
                
                var instance = $(this);
                $('.wconsumer-vimeo-widget-upload-form input[type="hidden"]', context).each(function() {
                    var name = $(this).attr('name');
                    if (name && name.indexOf('[vvid]') >= 0)
                        $(this).val(instance.attr('data-video-id'));
                });
            });
        }
    };

    Drupal.behaviors.wconsumer_vimeoViewForm = {
        attach: function (context) {
        }
    };
})(jQuery);
