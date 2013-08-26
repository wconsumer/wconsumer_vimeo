<?php

use Drupal\wconsumer\Wconsumer;
use Drupal\wconsumer\Service\Exception;

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

    if ($replace_id) {
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

?>