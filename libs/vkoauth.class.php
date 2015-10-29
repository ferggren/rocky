<?php
class VkOAuth extends OAuthBase {
    protected static $prefix = 'vk';
    protected static $table = 'users_oauth_vk';
    protected $entry = false;

    public function getRedirectLink() {
        if (!($config = self::getConfig())) {
            return false;
        }

        $link  = 'https://oauth.vk.com/authorize?';
        $link .= 'client_id=' . $config['app_id'];
        $link .= '&scope=' . $config['app_scope'];
        $link .= '&redirect_uri=' . rawurlencode($config['redirect']);
        $link .= '&response_type=code';
        $link .= '&v=5.34';

        return $link;
    }

    public function processAuth() {
        if (!($config = self::getConfig())) {
            return false;
        }

        $code = isset($_GET['code']) ? $_GET['code'] : '';

        if (!preg_match('#^[0-9a-zA-Z_-]++$#', $code)) {
            return false;
        }

        $link  = 'https://oauth.vk.com/access_token?';
        $link .= 'client_id=' . $config['app_id'];
        $link .= '&client_secret=' . $config['app_secret'];
        $link .= '&code=' . $code;
        $link .= '&redirect_uri=' . rawurlencode($config['redirect']);

        if (!($info = @file_get_contents($link))) {
            return false;
        }

        if (!is_array($info = json_decode($info, true))) {
            return false;
        }

        if (!isset($info['user_id']) || !isset($info['access_token'])) {
            return false;
        }

        if ($this->loadEntry($info['user_id'], $info['access_token'])) {
            return true;
        }

        $link  = 'https://api.vk.com/method/users.get?';
        $link .= '&user_ids=' . $info['user_id'];
        $link .= '&fields=photo_200,photo_200_orig';
        $link .= '&access_token=' . $info['access_token'];

        if (!($user_info = @file_get_contents($link))) {
            return false;
        }

        if (!is_array($user_info = json_decode($user_info, true))) {
            return false;
        }

        if (!isset($user_info['response'][0])) {
            return false;
        }

        $user_info = $user_info['response'][0];

        $photo = '';

        if (isset($user_info['photo_200'])) {
            $photo = $user_info['photo_200'];
        }

        if (isset($user_info['photo_200_orig'])) {
            $photo = $user_info['photo_200_orig'];
        }

        $entry = new Database(static::$table);
        $entry->user_id = 0;
        $entry->vk_id = $user_info['uid'];
        $entry->vk_name = trim($user_info['first_name'] . ' ' . $user_info['last_name']);
        $entry->vk_photo = $photo;
        $entry->last_login = time();
        $entry->access_token = $info['access_token'];

        if (!$entry->save()) {
            return false;
        }

        $this->entry = $entry;

        return true;
    }

    public function getUserInfo() {
        if (!$this->entry) {
            return false;
        }

        return array(
            'oauth_id' => $this->entry->vk_id,
            'photo' => $this->entry->vk_photo,
            'name' => $this->entry->vk_name,
            'user_id' => $this->entry->user_id,
        );
    }

    protected function loadEntry($oauth_id, $access_token) {
        $entry = Database::from(static::$table);
        $entry->where(static::$prefix . '_id', '=', $oauth_id);
        $entry = $entry->get();

        if (count($entry) != 1) {
            return false;
        }

        $entry = $entry[0];
        $entry->access_token = $access_token;
        $entry->last_login = time();
        $entry->save();

        $this->entry = $entry;

        return true;
    }
}
?>