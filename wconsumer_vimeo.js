(function ($) {
    
    function drawPieChart() {
        if (document.getElementById('wconsumer-vimeo-upload-form-quota') == null)
            return;
            
        var freespace = [[0, parseInt($('#wconsumer-vimeo-upload-form-quota .freespace').text())]];
        var usedspace = [[0, parseInt($('#wconsumer-vimeo-upload-form-quota .usedspace').text())]];
        var graph;
        
        graph = Flotr.draw(document.getElementById('wconsumer-vimeo-upload-form-quota'), 
            [
                {
                    data: freespace,
                    label: 'Free Space'
                }, 
                {
                    data: usedspace,
                    label: 'Used Space'
                }
            ],
            {
                title: 'Quota',
                HtmlText: false,
                grid: {
                    verticalLines: false,
                    horizontalLines: false
                },
                xaxis: {
                    showLabels: false
                },
                yaxis: {
                    showLabels: false
                },
                pie: {
                    show: true,
                    explode: 6
                },
                mouse: {
                    track: true
                },
                legend: {
                    position: 'se',
                    backgroundColor: '#D2E8FF'
                }
            }
        );
    }
    
    function initTextFieldPlaceholder() {
        $('.wconsumer-vimeo-content-bar input[type="text"], .wconsumer-vimeo-content-bar textarea').each(function() {
            var placeholder = $(this).attr('data-placeholder');
            
            if (placeholder) {
                this.value = placeholder;
            }
        });
    }
    
    Drupal.behaviors.wconsumer_vimeoEditForm = {
        attach: function (context, settings) {
            $(".wconsumer-vimeo-browser-button a").colorbox({'overlayClose': false, 'closeButton': true, 'escKey': false, 
                'onComplete': function() {
                    initTextFieldPlaceholder();
                }
            });
        }
    },
    Drupal.behaviors.wconsumer_vimeoChooseVideoPopup = {
        attach: function (context, settings) {
            var file_uploader = null;
            
            $('.wconsumer-vimeo-video-service span').die('click');
            $('.wconsumer-vimeo-video-service span').live('click', function() {
                $('.wconsumer-vimeo-video-service a').hide();
                $(this).parent().find('a').css('display', 'block');
            });
            
            $('input[type="text"], textarea').die('focus');
            $('input[type="text"], textarea').die('blur');
            $('input[type="text"], textarea').live('focus', function() {
                var placeholder = $(this).attr('data-placeholder');
                
                if (placeholder) {
                    this.value = (this.value == placeholder?'':this.value);
                }
                
            }).live('blur', function() {
                var placeholder = $(this).attr('data-placeholder');
                
                if (placeholder) {
                    this.value = (this.value == ''?placeholder:this.value);
                }
            });
            
            // Choose video
            $('#wconsumer-vimeo-videos ul li').die('click');
            $('#wconsumer-vimeo-videos ul li').live('click', function() {
                $('#wconsumer-vimeo-videos ul li').removeClass('selected');
                $(this).addClass('selected');
            });
            
            // Click choose button
            $('#wconsumer-vimeo-videos ul li').die('click');
            $('#wconsumer-vimeo-videos ul li').live('click', function() {
                $('#wconsumer-vimeo-videos ul li').removeClass('selected');
                $(this).addClass('selected');
            });
            
            // Click save button
            $('.wconsumer-vimeo-title-bar a#wconsumer_choose_video').die('click');
            $('.wconsumer-vimeo-title-bar a#wconsumer_choose_video').live('click', function() {
                if ($('#wconsumer-vimeo-videos ul li.selected').length != 0) {
                    var selectedItem = $('#wconsumer-vimeo-videos ul li.selected');
                    var vvid = selectedItem.attr('data-video-id');
                    
                    $.colorbox.openLoading();
                    $('.wconsumer-vimeo-view-video').load('/wconsumer_vimeo/videos/preview?video_id=' + vvid, function() {
                        $('#wconsumer-vimeo-field-video-id').find('input[type="hidden"]').val(vvid);
                        $.colorbox.close();
                    });
                }
                return false;
            });
            
            // Click cancel button
            $('.wconsumer-vimeo-title-bar a#wconsumer_choose_video_cancel').die('click');
            $('.wconsumer-vimeo-title-bar a#wconsumer_choose_video_cancel').live('click', function() {
                $.colorbox.close();
                return false;
            });
            
            // Clicking menus on left sidebar.
            $('.wconsumer-vimeo-video-service a, .wconsumer-vimeo-videos-view-mode a, a.wconsumer-vimeo-videos-view-mode-upload').die('click');
            $('.wconsumer-vimeo-video-service a, .wconsumer-vimeo-videos-view-mode a, a.wconsumer-vimeo-videos-view-mode-upload').live('click', function() {
                $.colorbox.openLoading();
                $.ajax({
                    'url': $(this).attr('href'),
                    'type': 'get',
                    'dataType': 'json',
                    'success': function(response) {
                        var html = response['html'];
                        $.colorbox({
                            html: html, 
                            'overlayClose': false, 'escKey': false,'closeButton': true, 
                            'onComplete': function() {
                                //if (!file_uploader)
                                file_uploader = new uploader('wconsumer_vimeo_video_file_container', 'wconsumer_vimeo_video_file_status', '/wconsumer_vimeo/videos/douploadfile', 'wconsumer_vimeo_video_list', 'wconsumer_vimeo_delete_video', 'wconsumer_vimeo_video_file');
                                drawPieChart();
                                initTextFieldPlaceholder();
                            }
                        });
                    }
                });
                return false;
            });
            
            // When uploading video
            $('.wconsumer-vimeo-upload-form form').die('submit');
            $('.wconsumer-vimeo-upload-form form').live('submit', function() {
                var data = $(this).serialize();
                $.colorbox.openLoading();
                $.ajax({
                    'url': $(this).attr('action'),
                    'type': $(this).attr('method'),
                    'dataType': 'json',
                    'data': data,
                    'success': function(response) {
                        var html = response['html'];
                        var success = response['success'];
                        $.colorbox({
                            html: html, 
                            'overlayClose': false, 'escKey': false, 'closeButton': true, 
                            'onComplete': function() {
                                if (!success) {
                                    file_uploader = new uploader('wconsumer_vimeo_video_file_container', 'wconsumer_vimeo_video_file_status', '/wconsumer_vimeo/videos/douploadfile', 'wconsumer_vimeo_video_list', 'wconsumer_vimeo_delete_video', 'wconsumer_vimeo_video_file');
                                } else {
                                    $('.wconsumer-vimeo-video-service.vimeo a.video-upload').trigger('click');
                                }
                                drawPieChart();
                                initTextFieldPlaceholder();
                            }
                        });
                    }
                });
                return false;
            });
            
            // Pagination
            $('.wconsumer-vimeo-widget-pagination a').die('click');
            $('.wconsumer-vimeo-widget-pagination a').live('click', function() {
                $.colorbox.openLoading();
                $.ajax({
                    'url': $(this).attr('href'),
                    'type': 'get',
                    'dataType': 'json',
                    'success': function(response) {
                        var html = response['html'];
                        $.colorbox({
                            html: html, 
                            'overlayClose': false, 'escKey': false, 'closeButton': true, 
                            'onComplete': function() {
                                initTextFieldPlaceholder();
                            }
                        });
                    }
                });
                return false;
            });
        }
    };
})(jQuery);
