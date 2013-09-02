<?php

use Drupal\wconsumer\Wconsumer;
use Drupal\wconsumer\Service\Exception;

/**
 * @file
 * Implement an image field, based on the file module's file field.
 */

/**
 * Implements hook_field_info().
 */
function wconsumer_vimeo_field_info() {
    return array(
        'wconsumer_vimeo' => array(
            'label' => t('Wconsumer Vimeo'),
            'description' => t('This field stores the ID of an image file as an integer value.'),
            'settings' => array(
                'uri_scheme' => variable_get('file_default_scheme', 'public'),
                'display_field' => 0,
                'display_default' => 0,
            ),
            'instance_settings' => array(
                'file_extensions' => 'flv',
                'file_directory' => '',
                'max_filesize' => '',
                'display_default' => 0,
                'description_field' => 0,
            ),
            'default_widget' => 'wconsumer_vimeo_vimeo',
            'default_formatter' => 'wconsumer_vimeo',
        ),
    );
}

/**
 * Implements hook_field_settings_form().
 */
function wconsumer_vimeo_field_settings_form($field, $instance, $has_data) {
    $form = file_field_settings_form($field, $instance, $has_data);
    return $form;
}

/**
 * Implements hook_field_instance_settings_form().
 */
function wconsumer_vimeo_field_instance_settings_form($field, $instance) {
    $settings = $instance['settings'];

    // Use the file field instance settings form as a basis.
    $form = file_field_instance_settings_form($field, $instance);
    return $form;
}

/**
 * Implements hook_field_load().
 */
function wconsumer_vimeo_field_load($entity_type, $entities, $field, $instances, $langcode, &$items, $age) {
    
    $fids = array();
    foreach ($entities as $id => $entity) {
        // Load the files from the files table.
        foreach ($items[$id] as $delta => $item) {
            if (!empty($item['fid'])) {
                $fids[] = $item['fid'];
            }
        }
    }
    $files = file_load_multiple($fids);

    foreach ($entities as $id => $entity) {
        
        foreach ($items[$id] as $delta => $item) {
        // If the file does not exist, mark the entire item as empty.
            if ((empty($item['fid']) || !isset($files[$item['fid']])) && empty($item['vvid'])) {
                $items[$id][$delta] = NULL;
            } else {
                if (!empty($item['fid']) && !isset($files[$item['fid']]))
                    $items[$id][$delta] = array_merge($item, (array) $files[$item['fid']]);
                else if (!empty($item['vvid'])) {
                    $items[$id][$delta] = $item;
                }
            }
        }
    }
}

/**
 * Form submission handler for upload/remove button of wconsumer_vimeo_field_widget_form().
 *
 * This runs in addition to and after file_managed_file_submit().
 *
 */
function wconsumer_vimeo_field_widget_submit($form, &$form_state) {
    file_field_widget_submit($form, $form_state);
}

/**
 * Implements hook_field_prepare_view().
 */
function wconsumer_vimeo_field_prepare_view($entity_type, $entities, $field, $instances, $langcode, &$items) {
    file_field_prepare_view($entity_type, $entities, $field, $instances, $langcode, $items);
}

/**
 * Implements hook_field_presave().
 */
function wconsumer_vimeo_field_presave($entity_type, $entity, $field, $instance, $langcode, &$items) {
    foreach ($items as &$item) {
        try {
            if ($item['upload_type'] == 1) {
                if (!empty($item['fid'])) {
                    file_field_presave($entity_type, $entity, $field, $instance, $langcode, $items);
                    
                    $file = file_load($item['fid']);
                    $file_path = wconsumer_vimeo_get_file_path($file);
                    
                    $item['vvid'] = wconsumer_vimeo_upload_video($file_path);
                    
                    if (!empty($item['title'])) {
                        wconsumer_vimeo_call_vimeo_api('vimeo.videos.setTitle', array('title' => $item['title'], 'video_id' => $item['vvid']));
                    }
                    
                    if (!empty($item['description'])) {
                        wconsumer_vimeo_call_vimeo_api('vimeo.videos.setDescription', array('description' => $item['description'], 'video_id' => $item['vvid']));
                    }
                    
                    if (!empty($item['tags'])) {
                        wconsumer_vimeo_call_vimeo_api('vimeo.videos.addTags', array('tags' => $item['tags'], 'video_id' => $item['vvid']));
                    }
                    
                    file_delete($file);
                    @unlink($file_path);
                    unset($item['fid']);
                } else {
                    $item['vvid'] = $item['old_vvid'];
                }
            } else if ($item['upload_type'] == 2) { // Choose video from vimeo
                if (empty($item['vvid'])) {
                    $item['vvid'] = $item['old_vvid'];
                }
            }
            
            $video_info = wconsumer_vimeo_get_video($item['vvid']);
            $item['info'] = serialize($video_info);
            
            unset($item['old_upload_type']);
            unset($item['old_vvid']);
            unset($item['old_fid']);
            
        }
        catch (Exception\NotLoggedInUser $e) {
            throw new \Exception\NotLoggedInUser("Please sign up or log in to upload video");
        }
        catch (Exception\ServiceInactive $e) {
            throw new \Exception\ServiceInactive("The Vimeo service integration is currently deactivated by the website administrator");
        }
        catch (Exception\NoUserCredentials $e) {
            throw new \Exception\NoUserCredentials("Before you can upload your video you need to connect with Vimeo in your profile");
        }
        catch (Exception\AdditionalScopesRequired $e) {
            throw new \Exception\AdditionalScopesRequired("Please re-connect to Vimeo in your profile to see your vimeo activity");
        }
    }
}

/**
 * Implements hook_field_insert().
 */
function wconsumer_vimeo_field_insert($entity_type, $entity, $field, $instance, $langcode, &$items) {
    //file_field_insert($entity_type, $entity, $field, $instance, $langcode, $items);
}

/**
 * Implements hook_field_update().
 */
function wconsumer_vimeo_field_update($entity_type, $entity, $field, $instance, $langcode, &$items) {
    //file_field_update($entity_type, $entity, $field, $instance, $langcode, $items);
}

/**
 * Implements hook_field_delete().
 */
function wconsumer_vimeo_field_delete($entity_type, $entity, $field, $instance, $langcode, &$items) {
    //file_field_delete($entity_type, $entity, $field, $instance, $langcode, $items);
}

/**
 * Implements hook_field_delete_revision().
 */
function wconsumer_vimeo_field_delete_revision($entity_type, $entity, $field, $instance, $langcode, &$items) {
    file_field_delete_revision($entity_type, $entity, $field, $instance, $langcode, $items);
}

/**
 * Implements hook_field_is_empty().
 */
function wconsumer_vimeo_field_is_empty($item, $field) {
    return (empty($item['fid']) && empty($item['vvid']));
}

/**
 * Implements hook_field_widget_info().
 */
function wconsumer_vimeo_field_widget_info() {
    return array(
        'wconsumer_vimeo_vimeo' => array(
            'label' => t('Vimeo'),
            'field types' => array('wconsumer_vimeo'),
            'settings' => array(
                'progress_indicator' => 'throbber'
            ),
            'behaviors' => array(
                'multiple values' => FIELD_BEHAVIOR_CUSTOM,
                'default value' => FIELD_BEHAVIOR_NONE,
            ),
        ),
    );
}

/**
 * Implements hook_field_widget_settings_form().
 */
function wconsumer_vimeo_field_widget_settings_form($field, $instance) {
    $widget = $instance['widget'];
    $settings = $widget['settings'];

    // Use the file widget settings form.
    $form = file_field_widget_settings_form($field, $instance);

    return $form;
}

/**
 * Implements hook_field_widget_form().
 */
function wconsumer_vimeo_field_widget_form(&$form, &$form_state, $field, $instance, $langcode, $items, $delta, $element) {

    // Add display_field setting to field because file_field_widget_form() assumes it is set.
    $field['settings']['display_field'] = 0;

    $elements = file_field_widget_form($form, $form_state, $field, $instance, $langcode, $items, $delta, $element);
    
    foreach (element_children($elements) as $delta) {
        $elements[$delta]['#process'][] = 'wconsumer_vimeo_field_widget_process';
    }
    
    return $elements;
}

/**
 * An element #process callback for the vimeo_vimeo field type.
 *
 * Expands the vimeo_vimeo type to include the alt and title fields.
 */
function wconsumer_vimeo_field_widget_process($element, &$form_state, $form) {
    $element['#attached']['css'][] = drupal_get_path('module', 'wconsumer_vimeo') . '/wconsumer_vimeo.css';
    $element['#attached']['js'][] = drupal_get_path('module', 'wconsumer_vimeo') . '/wconsumer_vimeo.js';
    
    $item = $element['#value'];
    $element = file_field_widget_process($element, $form_state, $form);
    
    foreach (array('upload_button', 'remove_button') as $key) {
        $element[$key]['#submit'][] = 'wconsumer_vimeo_field_widget_submit';
        $element[$key]['#limit_validation_errors'] = array(array_slice($element['#parents'], 0, -1));
    }

    // Add the additional alt and title fields.
    $element['title'] = array(
        '#title' => t('Video Title'),
        '#type' => 'textfield',
        '#default_value' => isset($item['title']) ? $item['title'] : '',
        '#description' => t('Videos are more interesting when they have creative titles. We know you can do better than "My Video."'),
        '#maxlength' => 512,
        '#weight' => -2,
        '#access' => (bool) $item['fid']
    );
    
    $element['description'] = array(
        '#type' => 'textarea',
        '#title' => t('Video Description'),
        '#default_value' => isset($item['description']) ? $item['description'] : '',
        '#description' => t('Tell the story behind your video, and how you were involved in making it. (No HTML, but links are OK.)'),
        '#maxlength' => 1024,
        '#weight' => -1,
        '#access' => (bool) $item['fid']
    );
    
    $element['tags'] = array(
        '#type' => 'textfield',
        '#title' => t('Video Tags'),
        '#default_value' => isset($item['tags']) ? $item['tags'] : '',
        '#description' => t('Add some relevant keywords to make your video easier to find. (Separate your tags with commas, please.)'),
        '#maxlength' => 1024,
        '#weight' => -1,
        '#access' => (bool) $item['fid']
    );
    
    $element['vvid'] = array(
        '#type' => 'hidden',
        '#default_value' => isset($item['vvid']) ? $item['vvid'] : '',
    );
    
    $element['upload_type'] = array(
        '#type' => 'hidden',
        '#default_value' => isset($item['upload_type']) ? $item['upload_type'] : 1,
    );
    
    $element['old_upload_type'] = array(
        '#type' => 'hidden',
        '#default_value' => isset($item['upload_type']) ? $item['upload_type'] : 1,
    );
    
    $element['old_vvid'] = array(
        '#type' => 'hidden',
        '#default_value' => isset($item['vvid']) ? $item['vvid'] : 1,
    );
    
    $element['old_fid'] = array(
        '#type' => 'hidden',
        '#default_value' => isset($item['fid']) ? $item['fid'] : 0xffffffff,
    );
    
    $element['#theme'] = 'wconsumer_vimeo_widget';

    return $element;
}

/**
 * Returns HTML for an image field widget.
 *
 * @param $variables
 *   An associative array containing:
 *   - element: A render element representing the image field widget.
 *
 * @ingroup themeable
 */
function theme_wconsumer_vimeo_widget($variables) {
    $element = $variables['element'];
    $values = $element['#value'];
    
    $videos = wconsumer_vimeo_get_videos();
    
    $output = '';
    $output .= '<div class="wconsumer-vimeo-widget form-managed-file clearfix">';    
    if (!empty($values['vvid'])) {
        $video_id = $values['vvid'];
        $output .= '<div class="wconsumer-vimeo-view-video">';
        $oembed = wconsumer_vimeo_get_oembed($video_id, variable_get('wconsumer_vimeo_video_width_on_edit'));
        $output .= html_entity_decode($oembed->html);
        $output .= '</div>';// .wconsumer-vimeo-view-video
    }
    
    $values['upload_type'] = (empty($values['upload_type'])?1:$values['upload_type']);
    
    $output .= '<div class="wconsumer-vimeo-widget-upload-form">';
        // Upload Video from Local
        $output .= '<div id="wconsumer-vimeo-widget-upload-videos" class="wconsumer-vimeo-widget-upload-type" data-type="1"><span>1.</span><strong>Upload Video from Local</strong></div>';
        $output .= '<div class="wconsumer-vimeo-widget-upload-videos wconsumer-vimeo-widget-form" style="display:'.($values['upload_type']==1?'block':'none').'">';
            $output .= drupal_render_children($element);
        $output .= '</div>';// .wconsumer-vimeo-widget-upload-videos
        
        // Choose Video from Vimeo Site
        $output .= '<div id="wconsumer-vimeo-widget-existing-videos" class="wconsumer-vimeo-widget-upload-type" data-type="2"><span>2.</span><strong>Choose Video from Vimeo Site</strong></div>';
        $output .= '<div class="wconsumer-vimeo-widget-existing-videos wconsumer-vimeo-widget-form" style="display:'.($values['upload_type']==2?'block':'none').'">';
            $output .= '<strong>Please choose your videos below.</strong>';
            $output .= wconsumer_vimeo_display_videos($videos);
        $output .= '</div>';// .wconsumer-vimeo-widget-existing-videos
        
        // Capture Video
        $output .= '<div id="wconsumer-vimeo-widget-capture-video" class="wconsumer-vimeo-widget-upload-type" data-type="3"><span>3.</span><strong>Capture Video</strong></div>';
        $output .= '<div class="wconsumer-vimeo-widget-capture-video wconsumer-vimeo-widget-form" style="display:'.($values['upload_type']==3?'block':'none').'">';
            $output .= '<video autoplay="" id="wconsumer-vimeo-widget-videostream" class="wconsumer-vimeo-widget-videostream"></video>';
            $output .= '<div class="wconsumer-vimeo-widget-capture-buttons">';
                //$output .= '<input type="file" name="video" accept="video/*" capture />';
                $output .= '<a id="wconsumer-vimeo-widget-capture-button" href="#" class="button">Capture video</a><a id="wconsumer-vimeo-widget-capture-stop-button" href="#" class="button">Stop</a>';
            $output .= '</div>';
        $output .= '</div>';// .wconsumer-vimeo-widget-capture-video
        //$output .= '<div class="clear"></div>';
    $output .= '</div>';// .wconsumer-vimeo-widget-upload-form
    
    $output .= '</div>';// .wconsumer-vimeo-widget
    
    return $output;
}

/**
 * Implements hook_field_formatter_info().
 */
function wconsumer_vimeo_field_formatter_info() {
    $formatters = array(
        'wconsumer_vimeo' => array(
            'label' => t('Vimeo'),
            'field types' => array('wconsumer_vimeo'),
            'settings' => array(),
        ),
    );
    return $formatters;
}

/**
 * Implements hook_field_formatter_settings_form().
 */
function wconsumer_vimeo_field_formatter_settings_form($field, $instance, $view_mode, $form, &$form_state) {
    return array();
}

/**
 * Implements hook_field_formatter_settings_summary().
 */
function wconsumer_vimeo_field_formatter_settings_summary($field, $instance, $view_mode) {
    return array();
}

/**
 * Implements hook_field_formatter_view().
 */
function wconsumer_vimeo_field_formatter_view($entity_type, $entity, $field, $instance, $langcode, $items, $display) {
    $element = array();
    foreach ($items as $delta => $item) {
        $element[$delta] = array(
            '#theme' => 'wconsumer_vimeo_formatter',
            '#file' => (object) $item,
            '#path' => '',
        );
    }
  
    return $element;
}

/**
 * Returns HTML for an vimeo field formatter.
 *
 * @param $variables
 *   An associative array containing:
 *   - item: Associative array of image data, which may include "uri", "alt",
 *     "width", "height", "title" and "attributes".
 *   - vimeo_style: An optional image style.
 *   - path: An array containing the link 'path' and link 'options'.
 *
 * @ingroup themeable
 */
function theme_wconsumer_vimeo_formatter($variables) {
    $video = $variables['file'];
    
    if (empty($video->vvid)) {
        return theme_wconsumer_vimeo_file_link($variables);
    }
    
    $oembed = wconsumer_vimeo_get_oembed($video->vvid, variable_get('wconsumer_vimeo_video_width_on_edit'));
    $video_info = unserialize($video->info);
    $video_info = array_pop($video_info);
    
    $output = '<div class="wconsumer-vimeo-view">';
        $output .= html_entity_decode($oembed->html);
        $output .= '<p>'.$video_info->description.'</p>';
        $output .= '<div class="wconsumer-vimeo-stats">';
            $output .= '<div class="wconsumer-vimeo-stats">';
            $output .= '<div class="wconsumer-vimeo-stats-views"><strong>Plays: </strong>'.$video_info->stats_number_of_plays.'</div>';
            $output .= '<div class="wconsumer-vimeo-stats-likes"><strong>Likes: </strong>'.$video_info->stats_number_of_likes.'</div>';
            $output .= '<div class="wconsumer-vimeo-stats-comments"><strong>Comments: </strong>'.$video_info->stats_number_of_comments.'</div>';
            $output .= '</div>'; // .wconsumer-vimeo-stats
    $output .= '</div>'; // .wconsumer-vimeo-view
    
    return $output;
}

function theme_wconsumer_vimeo_file_link($variables) {
    return theme_wconsumer_vimeo_link();
}

function theme_wconsumer_vimeo_link() {
    theme_file_link($variables);
}