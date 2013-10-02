<?php

use Drupal\wconsumer\Wconsumer;
use Drupal\wconsumer\Service\Exception;

define('WCONSUMER_VIMEO_TEMP_UPLOAD_DIR', 'temp');

function wconsumer_vimeo_throw_exception($message) {
    $block = array();
    return $block('<div class="messages error">'.htmlspecialchars($message).'</div>');
}

function wconsumer_vimeo_get_file_path($file) {
    $file_url = file_create_url($file->uri);
    $file_url = str_replace($GLOBALS['base_url'], '', file_create_url($file->uri));
    $file_url = urldecode($file_url);
    
    $file_path = DRUPAL_ROOT . $file_url;
    return $file_path;
}

function _wconsumer_vimeo_upload_one_file($endpoint, $ticket, $file, $chunk_id = 0) {
    /** @var Client $api */
    $api = Wconsumer::$vimeo->api();
    $rsp = $api->put($endpoint, array('Content-Length' => filesize($file)), fopen($file, 'r'))->send()->getBody();
    
    return $rsp;
}

/**
* 
* @param string $method The name of the method to call.
* @param array $params The parameters to pass to the method.
* @param string $request_method The HTTP request method to use.
* @return array The response from the API method
*/
function wconsumer_vimeo_call_vimeo_api($method = '', $params = array(), $request_method = 'GET') {
    /** @var Client $api */
    $api = Wconsumer::$vimeo->api();
    
    // Regular args
    $params['method'] = $method;
    $params['format'] = 'json';
    
    $query = '';
    foreach ($params as $key => $value) {
        if ($key == 'uploaded_file')
            continue;
        if (!empty($query))
            $query .= "&";
        $query .= "$key=$value";
    }
    $request_method = strtolower($request_method);
    
    if ($request_method == 'get') {
        $rsp = $api->get('?' . $query, null, array())->send()->getBody();
    } else if ($request_method == 'put') {
        $rsp = $api->put('?' . $query, null, fopen($params['uploaded_file'], 'r'))->send()->getBody();
    }
    
    return json_decode($rsp);
}


/**
 * Get information of a video
 *
 * @param int $video_id The video id
 * @return object video information
 */
function wconsumer_vimeo_get_video($video_id) {
    $api = Wconsumer::$vimeo->api();
    $rsp = $api->get('http://vimeo.com/api/v2/video/'.$video_id.'.json', array())->send()->getBody();
    
    return json_decode($rsp);
}

/**
 * Upload a video in one piece.
 *
 * @param string $file_path The full path to the file
 * @param boolean $use_multiple_chunks Whether or not to split the file up into smaller chunks
 * @param string $chunk_temp_dir The directory to store the chunks in
 * @param int $size The size of each chunk in bytes (defaults to 2MB)
 * @return int The video ID
 */
function wconsumer_vimeo_upload_video($file_path, $replace_id = null, $use_multiple_chunks = false, $chunk_temp_dir = '.', $size = 2097152) {
    if (!file_exists($file_path)) {
        return false;
    }

    // Figure out the filename and full size
    $path_parts = pathinfo($file_path);
    $file_name = $path_parts['basename'];
    $file_size = filesize($file_path);

    // Make sure we have enough room left in the user's quota
    $quota = wconsumer_vimeo_call_vimeo_api('vimeo.videos.upload.getQuota');
    if ($quota->user->upload_space->free < $file_size) {
        throw new \Exception('The file is larger than the user\'s remaining quota.', 707);
    }
    
    // Get an upload ticket
    $params = array();

    if (!empty($replace_id)) {
        $params['video_id'] = $replace_id;
    }
    
    $rsp = wconsumer_vimeo_call_vimeo_api('vimeo.videos.upload.getTicket', $params);
    $ticket = $rsp->ticket->id;
    $endpoint = $rsp->ticket->endpoint;

    // Make sure we're allowed to upload this size file
    if ($file_size > $rsp->ticket->max_file_size) {
        throw new \Exception('File exceeds maximum allowed size.', 710);
    }
    
    // Uploading files
    $api = Wconsumer::$vimeo->api();
    _wconsumer_vimeo_upload_one_file($endpoint, $ticket, $file_path);
    // Verify
    $verify = wconsumer_vimeo_call_vimeo_api('vimeo.videos.upload.verifyChunks', array('ticket_id' => $ticket));
    $chunks = array();
    // Make sure our file sizes match up
    /*foreach ($verify->ticket->chunks as $chunk_check) {
        $chunk = $chunks[$chunk_check->id];

        if ($chunk['size'] != $chunk_check->size) {
            // size incorrect, uh oh
            echo "Chunk {$chunk_check->id} is actually {$chunk['size']} but uploaded as {$chunk_check->size}<br>";
        }
    }*/

    // Complete the upload
    $complete = wconsumer_vimeo_call_vimeo_api('vimeo.videos.upload.complete', array('filename' => $file_name, 'ticket_id' => $ticket));
    // Clean up
    /*if (count($chunks) > 1) {
        foreach ($chunks as $chunk) {
            @unlink($chunk['file']);
        }
    }*/

    // Confirmation successful, return video id
    if ($complete->stat == 'ok') {
        return $complete->ticket->video_id;
    }
    else if ($complete->err) {
        throw new \Exception($complete->err->msg, $complete->err->code);
    }
}

function wconsumer_vimeo_get_videos($page = 0) {
    $api = Wconsumer::$vimeo->api();
    $videos = wconsumer_vimeo_call_vimeo_api('vimeo.videos.getAll', array('page' => $page, 'per_page' => variable_get('wconsumer_vimeo_videos_per_page'), 'full_response' => true));
    return $videos;
}

function wconsumer_vimeo_get_oembed($video_id = 0, $width = 640) {
    if (empty($video_id))
        return '';
    
    $api = Wconsumer::$vimeo->api();
    
    $oembed_endpoint = 'http://vimeo.com/api/oembed';
    $video_url = "http://vimeo.com/$video_id";
    
    $rsp = $api->get('http://vimeo.com/api/oembed.json?url=' . rawurlencode($video_url) . '&width=' . $width, array())->send()->getBody();
    $oembed = json_decode($rsp);
    
    return $oembed;
}

function wconsumer_vimeo_display_videos($videos) {
    $view_mode = (empty($_GET['viewmode'])?'grid':$_GET['viewmode']);
    
    $output = '<div id="wconsumer-vimeo-videos" class="'.$view_mode.'">';
        if (!empty($videos->videos->video)) :
            $output .= '<ul>';
            foreach ($videos->videos->video as $i => $video) {
                $output .= '<li data-video-id="'.$video->id.'" style="'.($i % 4 == 0?'clear: both':'').'" class="'.($i % 4 == 3?'last':'').'">';
                    $output .= '<div class="selected-icon"><img src="/'. drupal_get_path('module', 'wconsumer_vimeo') . '/images/' .'icon_video_play.png" /></div>';
                    $output .= '<div class="inner">';
                        $output .= '<img width="175" height="131" src="'.$video->thumbnails->thumbnail[1]->_content.'" />';
                        $output .= '<div class="inner-info">';
                            $output .= '<span>'.$video->title.'</span>';
                            $output .= '<p>'.$video->description.'</p>';
                        $output .= '</div>';
                    $output .= '</div>';
                $output .= '</li>';
            }
            $output .= '</ul>';
            $output .= '<div class="clear"></div>';
            $output .= wconsumer_vimeo_create_pagination($videos->videos->page, $videos->videos->perpage, $videos->videos->total);    
        endif;
    $output .= '</div>';// .wconsumer-vimeo-widget-videos
    
    return $output;
}

function wconsumer_vimeo_create_pagination($cur_page = 1, $per_page, $totals) {
    if ($totals == 0)
        return  '';
    $total_pages = intval(($totals - 1)/ $per_page) + 1;
    $output = '<div class="wconsumer-vimeo-widget-pagination">';
    
    if ($total_pages != 1 && $cur_page != 1)
        $output .= wconsumer_vimeo_get_ajax_page_link('Prev', $cur_page - 1);
    for ($page = 1; $page <= $total_pages; $page++) {
        $output .= wconsumer_vimeo_get_ajax_page_link($page, $page, $page == $cur_page);
    }
    if ($total_pages != 1 && $cur_page != $total_pages)
        $output .= wconsumer_vimeo_get_ajax_page_link('Next', $cur_page + 1);
    $output .= '</div>';
    $output .= '<div class="clear"></div>';
    
    return $output;
}

function wconsumer_vimeo_get_ajax_page_link($link_text, $page, $selected = false) {
    $view_mode = (empty($_GET['viewmode'])?'grid':$_GET['viewmode']);
    $link = '<a href="'.url('wconsumer_vimeo/videos/browser') . "?page=$page&action=browser&viewmode=".$view_mode.'" class="'.($selected?'selected':'').'">'.$link_text.'</a>';    
    return $link;
}

function wconsumer_vimeo_popup_title_bar($action) {
    $output = '';
    $output .= '<div class="wconsumer-vimeo-title-bar">';
        if ($action == 'browser') {
            $output .= 'Choose or upload your video';
            $output .= '<a id="wconsumer_choose_video_cancel" href="#">CANCEL</a>';
            $output .= '<a id="wconsumer_choose_video" href="#">SAVE</a>';
        } else if ($action == 'upload')
            $output .= 'Upload new video';
    $output .= '</div>';
    return $output;
}

function wconsumer_vimeo_popup_left_sidebar() {
    $output = '';
    $output .= '<div class="wconsumer-vimeo-left-sidebar">';
        $output .= '<div class="wconsumer-vimeo-block-inner">';
            $output .= '<div class="wconsumer-vimeo-video-services">';
                $output .= '<div class="wconsumer-vimeo-video-service vimeo '.(empty($_GET['type']) || $_GET['type'] == 'vimeo'?'selected':'').'">';
                    $output .= '<a class="video-server-type" href="'.url('wconsumer_vimeo/videos/browser').'?type=vimeo">Vimeo</a>';
                $output .= '</div>';
                $output .= '<div class="wconsumer-vimeo-video-service dropbox '.($_GET['type'] == 'dropbox'?'selected':'').'">';
                    $output .= '<a class="video-server-type" href="'.url('wconsumer_vimeo/videos/browser').'?type=dropbox">Dropbox</a>';
                $output .= '</div>';
            $output .= '</div>';
        $output .= '</div>';
    $output .= '</div>';
    return $output;
}

function wconsumer_vimeo_popup_content_bar($action) {
    $output = '';
    $output .= '<div class="wconsumer-vimeo-content-bar">';
        $output .= '<div class="wconsumer-vimeo-block-inner">';
        if ($action == 'browser')
            $output .= wconsumer_vimeo_popup_list_videos();
        else if ($action == 'upload')
            $output .= wconsumer_vimeo_popup_upload_form();
        else if ($action == 'doupload') {
            $params = $_POST['video'];
            
            $error_message = array();
            if (empty($params['file'])) {
                $error_message[] = 'You must upload file for video.';
            }
            if (empty($params['title'])) {
                $error_message[] = 'Title is required.';
            }
            if (empty($params['description'])) {
                $error_message[] = 'Description is required.';
            }
            if (empty($params['tags'])) {
                $error_message[] = 'Tags is required.';
            }
            
            if (!empty($error_message)) {
                $output .= wconsumer_vimeo_popup_upload_form($error_message, false);
            } else {
                $vvid = wconsumer_vimeo_upload_video(WCONSUMER_VIMEO_TEMP_UPLOAD_DIR . '/' . $params['file']);
                wconsumer_vimeo_call_vimeo_api('vimeo.videos.setTitle', array('title' => $params['title'], 'video_id' => $vvid));
                wconsumer_vimeo_call_vimeo_api('vimeo.videos.setDescription', array('description' => $params['description'], 'video_id' => $vvid));
                wconsumer_vimeo_call_vimeo_api('vimeo.videos.addTags', array('tags' => $params['tags'], 'video_id' => $vvid));
                
                $output .= wconsumer_vimeo_popup_upload_form(array('Successfully uploaded!!!'), true);
            }
        }
        $output .= '</div>';
    $output .= '</div>';
    return $output;
}

function wconsumer_vimeo_popup_upload_form($error_message = array(), $success = false) {
    $quota = wconsumer_vimeo_call_vimeo_api('vimeo.videos.upload.getQuota');
    $output  = '<div class="wconsumer-vimeo-upload-form">';
        $output .= '<div id="wconsumer-vimeo-upload-form-quota">';
            $output .= '<span class="freespace">'.$quota->user->upload_space->free.'</span>';
            $output .= '<span class="usedspace">'.$quota->user->upload_space->used.'</span>';
        $output .= '</div>';
        $output .= "
            <!--[if IE]>
            <script type=\"text/javascript\" src=\"/".drupal_get_path('module', 'wconsumer_vimeo')."/js/flotr2/flashcanvas.js\"></script>
            <![endif]-->
            <script type=\"text/javascript\" src=\"/".drupal_get_path('module', 'wconsumer_vimeo')."/js/flotr2/flotr2.min.js\"></script>
        ";
        $output .= '<form method="post" action="'.url('wconsumer_vimeo/videos/browser').'?action=doupload">';
            // if there are some errors when uploading...
            if (!empty($error_message)) {
                $output .= '<div class="clearfix" id="console">';
                    $output .= '<div class="messages '.($success?'status':'error').'">';
                    foreach ($error_message as $i => $message) {
                        if ($i != 0)
                            $output .= '<br />';
                        $output .= $message;
                    }
                    $output .= '</div>';
                $output .= '</div>';
            }
            
            if (!empty($_POST['video']) && !$success)
                $params = $_POST['video'];
            else
                $params = array();
            
            $output .= '<div class="wconsumer-vimeo-field">';
                $output .= '<div class="wconsumer-vimeo-field-label">Video:</div>';
                $output .= '<div class="wconsumer-vimeo-field-value">';
                    $output .= '<div id="wconsumer_vimeo_video_file_status" style="display:'.(!empty($params['file'])?'none':'block').'">Drag the video file from a folder to a selected area ...</div>';
                    $output .= '<div id="wconsumer_vimeo_video_file_container" style="display:'.(!empty($params['file'])?'none':'block').'">';
                    $output .= '</div>';
                    $output .= '<div id="wconsumer_vimeo_video_list">'.(!empty($params['file'])?'Loaded : '.$params['file'].' size '.filesize(WCONSUMER_VIMEO_TEMP_UPLOAD_DIR . '/'. $params['file']).' B':'').'</div>';
                    $output .= '<a id="wconsumer_vimeo_delete_video" href="#" style="display:'.(!empty($params['file'])?'inline':'none').'">Delete</a>';
                    $output .= '<input type="hidden" name="video[file]" id="wconsumer_vimeo_video_file" value="'.(!empty($params['file'])?$params['file']:'').'" />';
                $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="wconsumer-vimeo-field">';
                $output .= '<div class="wconsumer-vimeo-field-label">Title:</div>';
                $output .= '<div class="wconsumer-vimeo-field-value">';
                    $output .= '<input type="text" name="video[title]" id="wconsumer_vimeo_video_title" />';
                $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="wconsumer-vimeo-field">';
                $output .= '<div class="wconsumer-vimeo-field-label">Description:</div>';
                $output .= '<div class="wconsumer-vimeo-field-value">';
                    $output .= '<textarea name="video[description]" id="wconsumer_vimeo_video_description"></textarea>';
                $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="wconsumer-vimeo-field">';
                $output .= '<div class="wconsumer-vimeo-field-label">Tags:</div>';
                $output .= '<div class="wconsumer-vimeo-field-value">';
                    $output .= '<input type="text" name="video[tags]" id="wconsumer_vimeo_video_tags" />';
                $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="wconsumer-vimeo-field">';
                $output .= '<div class="wconsumer-vimeo-field-label">&nbsp;</div>';
                $output .= '<div class="wconsumer-vimeo-field-value">';
                    $output .= '<button type="submit" id="wconsumer_vimeo_video_upload">Upload</button>';
                    $output .= '<button type="button" id="wconsumer_vimeo_video_cancel">Cancel</button>';
                $output .= '</div>';
            $output .= '</div>';
        $output .= '</form>';
    $output .= '</div>';
    return $output;
}

function wconsumer_vimeo_popup_list_videos() {
    $page = (empty($_GET['page'])?1:$_GET['page']);
    $videos = wconsumer_vimeo_get_videos($page);
    
    $output = '';
    $output .= '<div class="wconsumer-vimeo-display-videos-toolbar">';
        $output .= '<input type="text" id="wconsumer-vimeo-search-video-text" value="" data-placeholder="What video can monimus help you find?" value="What video can monimus help you find?" />';
        $output .= '<div class="wconsumer-vimeo-videos-view-mode">';
            $output .= '<strong>View<br />Mode</strong>';
            $output .= '<a href="'.url('wconsumer_vimeo/videos/browser').'?viewmode=grid&page='.$page.'" class="grid-mode"></a>';
            $output .= '<a href="'.url('wconsumer_vimeo/videos/browser').'?viewmode=list&page='.$page.'" class="list-mode"></a>';
        $output .= '</div>';
        $output .= '<a class="wconsumer-vimeo-videos-view-mode-upload" href="'.url('wconsumer_vimeo/videos/browser').'?action=upload&type=vimeo">UPLOAD VIDEO</a>';
    $output .= '</div>';
    $output .= wconsumer_vimeo_display_videos($videos);
    $output .= '<div class="clear"></div>';
    
    return $output;
}

function wconsumer_vimeo_videos_browser() {
    
    $action = (!empty($_GET['action'])?$_GET['action']:'browser');
    
    $output = '<div class="wconsumer-vimeo-popup-container">';
        $output .= wconsumer_vimeo_popup_title_bar($action);
        $output .= wconsumer_vimeo_popup_left_sidebar();
        $output .= wconsumer_vimeo_popup_content_bar($action);
    $output .= '</div>';
    
    if (empty($_GET['datatype']))
        echo json_encode(array('html' => $output));
    else if ($_GET['datatype'] == 'html') {
        echo $output;
    }
}

function wconsumer_vimeo_videos_do_upload() {
    if (!empty($_GET['action']) && $_GET['action'] == 'delete') {
        @unlink(WCONSUMER_VIMEO_TEMP_UPLOAD_DIR . '/' . $_GET['file']);
        exit;
    } else {
        // Destination folder for downloaded files
        // If the browser supports sendAsBinary () can use the array $ _FILES
        if (count($_FILES) > 0) {
            if (move_uploaded_file($_FILES['upload']['tmp_name'], WCONSUMER_VIMEO_TEMP_UPLOAD_DIR.'/'.$_FILES['upload']['name'])) {
                echo 'done';
            }
            exit();
        } else if(isset($_GET['up'])) {
            // If the browser does not support sendAsBinary ()
            if(isset($_GET['base64'])) {
                $content = base64_decode(file_get_contents('php://input'));
            } else {
                $content = file_get_contents('php://input');
            }

            $headers = getallheaders();
            $headers = array_change_key_case($headers, CASE_UPPER);
        
            if(file_put_contents(WCONSUMER_VIMEO_TEMP_UPLOAD_DIR.'/'.$headers['UP-FILENAME'], $content)) {
                echo 'done';
            }
            exit();
        }
    }
}

function wconsumer_vimeo_preview_video() {
    $oembed = wconsumer_vimeo_get_oembed($_GET['video_id'], variable_get('wconsumer_vimeo_video_width_on_edit'));
    $output .= html_entity_decode($oembed->html);
    echo $output;
    exit;
}