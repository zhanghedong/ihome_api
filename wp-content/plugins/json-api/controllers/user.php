<?php
/*
Controller name: User
Controller description: 用户相关操作api
*/
class JSON_API_User_Controller
{


    /**
     * @return array
     * 更新用户关注的产品类型
     * 参数：
     * {
     *      cookie:'',
     *     tags:[1,2,4]
     * }
     */
    public function update_my_follow() {
        global $json_api;

        if (!$json_api->query->cookie) {
            $json_api->error("You must include a 'cookie' authentication cookie. Use the `create_auth_cookie` Auth API method.");
        }

        $user_id = wp_validate_auth_cookie($json_api->query->cookie, 'logged_in');
        $tags = $json_api->query->tags;

        update_user_meta( $user_id, 'user_follow_tags', $tags );
       // var_dump(get_user_meta($user_id,  'user_follow_tags', true ));
        return array(
            "user_id" => $user_id
        );
    }
}
?>
