<?php
class JSON_API_Product_Controller
{

    public function get_products_by_tags($query = false, $wp_posts = false)
    {
        global $json_api, $post, $wp_query;
        $product_tag = $json_api->query->tags;
        $paged = $json_api->query->page;
        $count = $json_api->query->count;

        $args = array(
            'order' => 'ASC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'terms' => array($product_tag),
                    'field' => 'term_id',
                )
            ),
            'post_type' => 'product'
        );
        $this->set_posts_query($args);
        $new_post = null;
        while (have_posts()) {
            the_post();
            if ($wp_posts) {
                $new_post = $post;
            } else {
                $new_post = $this->format_post_data($post);
            }
            $output[] = (array)$new_post; //转为数组要不array_push会输出同一条
        }
//        $result = $this->posts_result($new_post);
//        $result['query'] = $query;
        return array(
//            paged => $paged,
//            count => $count,
            data => $output
        );
//        return $result;
    }

    public function get_products_by_category_id($wp_posts = false)
    { //根据分类ID取商品信息 TODO
        global $json_api, $post, $wp_query;
        $product_cat = $json_api->query->cat;
        $args = array('post_type' => 'product', 'product_cat' => $product_cat); /////自定义类别查询记下
        $output = array();
        query_posts($args);
        $new_post = null;
        while (have_posts()) {
            the_post();
            if ($wp_posts) {
                $new_post = $post;
            } else {
                $new_post = $this->format_post_data($post);
            }
            $output[] = (array)$new_post; //转为数组要不array_push会输出同一条
        }
        return array(
            data => $output
        );

        //输出SQL
        //  $results = new WP_Query( $args );
        //  echo $results->request;
    }

    public function get_tags()
    { //返回全部类别信息
        global $json_api;
        $parent = $json_api->query->parent;
        if (!$parent) {
            $parent = 0;
        }
        $categories = get_categories(array(
            'type' => 'product',
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => 0,
            'hierarchical' => 1,
            'taxonomy' => 'product_tag'));

        return array(
            data => $categories
        );
    }

    public function get_categorys()
    { //返回全部类别信息
        global $json_api;
        $parent = $json_api->query->parent;
        if (!$parent) {
            $parent = 0;
        }
        $categories = get_categories(array(
            'type' => 'product',
            'orderby' => 'name',
            // 'child_of'=>17,
            'order' => 'ASC',
//            'parent'  => (int)$parent,
            'hide_empty' => 0,
            'hierarchical' => 1,
            'taxonomy' => 'product_cat'));
//        echo get_query_var('product_cat').'=======';
//        $thisCat = get_category(get_query_var('product_cat'),false);
        return array(
            data => $categories
        );
    }

    public function get_products_by_user_id()
    {
        global $json_api;
        global $post_type;
        $post_type = 'product';
        $url = parse_url($_SERVER['REQUEST_URI']);
        $userId = $json_api->query->user_id;
        $defaults = array(
            'ignore_sticky_posts' => true,
            'post_type' => $post_type
        );
        $query = wp_parse_args($url['query']);
        unset($query['json']);
        unset($query['post_status']);
        $query = array_merge($defaults, $query);
        $posts = $json_api->introspector->get_posts($query);
        $result = $this->posts_result($posts);
        $result['query'] = $query;
        return $result;
    }


    public function get_product()
    {
        global $json_api, $post;
        $post_id =  $json_api->query->id;
        $post = get_post($post_id);
        $post = $this->format_post_detail_data($post);
        return array(
            data => $post
        );
    }

    protected function posts_result($posts)
    {
        global $wp_query;
        return array(
            'count' => count($posts),
            'count_total' => (int)$wp_query->found_posts,
            'pages' => $wp_query->max_num_pages,
            'posts' => $posts
        );
    }

    function set_value($key, $value)
    {
        global $json_api;
        if ($json_api->include_value($key)) {
            $this->$key = $value;
        } else {
            unset($this->$key);
        }
    }
    protected function format_post_detail_data($wp_post)
    {
        global $json_api, $post;
        $this->id = (int)$wp_post->ID;
        setup_postdata($wp_post);
        $this->set_value('type', $wp_post->post_type);
        $this->set_value('slug', $wp_post->post_name);
        $this->set_value('regular_price', get_meta('_regular_price', $this->id));
        $this->set_value('price', get_meta('_sale_price', $this->id));
        $this->set_value('url', get_permalink($this->id));
        $this->set_value('status', $wp_post->post_status);
        $this->set_value('content', get_the_content($this->id));
        $this->set_value('title', get_the_title($this->id));
        $this->set_thumbnail_value();
//        $this->set_value('title_plain', strip_tags(@$this->title));
//        do_action("json_api_{$this->type}_constructor", $this);
        return $this;

    }
    protected function format_post_data($wp_post)
    {
        global $json_api, $post;
        $this->id = (int)$wp_post->ID;
        setup_postdata($wp_post);
        $this->set_value('type', $wp_post->post_type);
        $this->set_value('slug', $wp_post->post_name);
        $this->set_value('regular_price', get_meta('_regular_price', $this->id));
        $this->set_value('price', get_meta('_sale_price', $this->id));
        $this->set_value('url', get_permalink($this->id));
        $this->set_value('status', $wp_post->post_status);
        $this->set_value('title', get_the_title($this->id));
        $this->set_thumbnail_value();
//        $this->set_value('title_plain', strip_tags(@$this->title));
//        do_action("json_api_{$this->type}_constructor", $this);
        return $this;

    }

    function set_thumbnail_value()
    {
        global $json_api;
        if (!$json_api->include_value('thumbnail') ||
            !function_exists('get_post_thumbnail_id')
        ) {
            unset($this->thumbnail);
            return;
        }
        $attachment_id = get_post_thumbnail_id($this->id);
        if (!$attachment_id) {
            unset($this->thumbnail);
            return;
        }
        $thumbnail_size = $this->get_thumbnail_size();
//        $this->thumbnail_size = $thumbnail_size;
        $attachment = $json_api->introspector->get_attachment($attachment_id);
        $image = $attachment->images[$thumbnail_size];
        $this->thumbnail = $image->url;
//        $this->thumbnail_images = $attachment->images;
    }


    function get_thumbnail_size()
    {
        global $json_api;
        if ($json_api->query->thumbnail_size) {
            return $json_api->query->thumbnail_size;
        } else if (function_exists('get_intermediate_image_sizes')) {
            $sizes = get_intermediate_image_sizes();
            if (in_array('post-thumbnail', $sizes)) {
                return 'post-thumbnail';
            }
        }
        return 'thumbnail';
    }

    protected function set_posts_query($query = false)
    {
        global $json_api, $wp_query;

        if (!$query) {
            $query = array();
        }

        $query = array_merge($query, $wp_query->query);

        if ($json_api->query->page) {
            $query['paged'] = $json_api->query->page;
        }

        if ($json_api->query->count) {
            $query['posts_per_page'] = $json_api->query->count;
        }

        if ($json_api->query->post_type) {
            $query['post_type'] = $json_api->query->post_type;
        }

        if (!empty($query)) {
            query_posts($query);
            do_action('json_api_query', $wp_query);
        }
    }
}
?>
