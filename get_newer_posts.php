<?php
add_action('rest_api_init', 'register_rest_newer_posts');

function register_rest_newer_posts() {
    register_rest_route('api/v1', 'newer-post', [
        'methods'  => WP_REST_SERVER::READABLE,
        'callback' => 'rest_newer_posts_callback',
    ]);
}

function rest_newer_posts_callback($data) {
    $town_category    = isset($data['t']) ? sanitize_text_field($data['t']) : '';
    $section_category = isset($data['s']) ? sanitize_text_field($data['s']) : '';
    $page_number      = isset($data['p']) ? absint(sanitize_text_field($data['p'])) : 1;

    if (empty($town_category)) {
        return array();
    }

    $section_categories   = ($section_category !== '') ? array($section_category) : array(35, 3, 36, 5, 7, 37, 4);
    $departament_categories = array(39);

    $response = array();

    try {
        foreach ($section_categories as $section_cat) {
            $args = array(
                'posts_per_page' => 10,
                'paged'          => $page_number,
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'tax_query'      => array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => absint($town_category),
                    ),
                    array(
                        'taxonomy' => 'category',
                        'field'    => 'term_id',
                        'terms'    => absint($section_cat),
                    ),
                ),
            );

            $posts = new WP_Query($args);

            while ($posts->have_posts()) {
                $posts->the_post();
                
                $categories = get_the_category();
                $post_category = array();

                $current_section_category = '';
                foreach ($categories as $category_id) {
                    $category = get_category($category_id);
                    if (in_array($category->term_id, $section_categories)) {
                        $current_section_category = $category->term_id;
                    }
                    if ($category->name == 'Pueblo' || 
                        $category->term_id == $town_category || 
                        in_array($category->term_id, $section_categories) || 
                        in_array($category->term_id, $departament_categories)
                    ) {
                        continue;
                    }
                    $post_category[] = array(
                        'id'   => $category->term_id,
                        'name' => $category->name,
                    );
                }

                $post_id           = get_the_ID();
                $html_content      = get_the_content();
                $feature_image_url = get_the_post_thumbnail_url($post_id);
                $image_urls        = get_post_images($html_content);
                $modified_date     = get_the_modified_date('Y-m-d H:i:s', $post_id);

                $response[] = array(
                    'id'                  => $post_id,
                    'title'               => get_the_title(),
                    'content'             => remove_html_comments($html_content),
                    'featured_img_url'   => $feature_image_url,
                    'images'             => $image_urls,
                    'categories'         => $post_category,
                    'section_category_id' => $current_section_category,
                    'modified_date'      => $modified_date,
                );
            }
            wp_reset_postdata();
        }
    } catch (Exception $e) {
        return new WP_Error('rest_exception', esc_html__('Error interno en el servidor.'), array('status' => 500));
    }

    return rest_ensure_response($response);
}

function get_post_images($content) {
    $pattern = '/<img [^>]*src=["\']([^"\']+)["\'][^>]*>/i';
    preg_match_all($pattern, $content, $image_post_urls);
    $image_urls = array_merge(array(get_the_post_thumbnail_url()), $image_post_urls[1]);
    return $image_urls;
}

function remove_html_comments($content) {
    return preg_replace('/<!--(.*?)-->/s', '', $content);
}
