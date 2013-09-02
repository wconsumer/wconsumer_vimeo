
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
            
            function onFailSoHard(e){
                if(e.code==1){
                    alert('User denied access to their camera');
                }else{
                    alert('getUserMedia() not supported in your browser.');
                }
            }
                
            var video = document.querySelector('#wconsumer-vimeo-widget-videostream');
            var button=document.querySelector('#wconsumer-vimeo-widget-capture-button');
            var localMediaStream=null;
            
            $('#wconsumer-vimeo-widget-capture-button').live('click', function(e){
                if(navigator.getUserMedia){
                    navigator.getUserMedia('video',function(stream){
                        video.src=stream;
                        video.controls=true;
                        localMediaStream=stream;
                    },onFailSoHard);
                } else if(navigator.webkitGetUserMedia){
                    navigator.webkitGetUserMedia({video:true}, function(stream){
                        video.src=window.webkitURL.createObjectURL(stream);
                        video.controls=true;
                        localMediaStream=stream;
                    },onFailSoHard);
                }else{
                    onFailSoHard({target:video});
                }
                return false;
            });
            $('#wconsumer-vimeo-widget-capture-stop-button').live('click',function(e){
                video.pause();
                localMediaStream.stop();
                return false;
            });
        
        }
    };

    Drupal.behaviors.wconsumer_vimeoViewForm = {
        attach: function (context) {
        }
    };
})(jQuery);
