<?php
abstract class OAuthBase {
    protected static $prefix = false;
    protected static $table = false;
    protected $entry = false;

    public abstract function getRedirectLink();
    public abstract function processAuth();
    public abstract function getUserInfo();

    public function getLinkedUser() {
        if (!($info = $this->getUserInfo())) {
            return false;
        }

        return (int)$info['user_id'];
    }

    public function linkAccount($user_id) {
        if (!$this->entry) {
            return false;
        }

        $this->entry->user_id = $user_id;
        $this->entry->save();

        return true;
    }

    public function unlinkAccount() {
        if (!$this->entry) {
            return false;
        }

        $this->unlinkAllAccount($this->entry->user_id);
        return true;
    }

    public function unlinkAccountFromUser($user_id) {
        $this->unlinkAllAccount($user_id);
        return true;
    }

    public function exportPhoto() {
        if (!($info = $this->getUserInfo())) {
            return false;
        }

        if (!$info['photo']) {
            return false;
        }

        $photo = $info['photo'];

        if (Config::get('auth.oauth_export_photo') == 'url') {
            return $photo;
        }

        if (Config::get('auth.oauth_export_photo') != 'export') {
            return false;
        }

        if (!preg_match('#^(\d++)x(\d++)$#', Config::get('auth.oauth_export_photo_min_size'), $data)) {
            return false;
        }

        $image_min_w = $data[1];
        $image_min_h = $data[2];

        if (!($buffer = @file_get_contents($photo))) {
            return false;
        }

        if (strlen($buffer) < 1024) {
            return false;
        }

        $tmp_path = ROOT_PATH . '/tmp/' . md5(microtime(true) . $photo) . '.photo.tmp';

        if (!($file = fopen($tmp_path, 'wb'))) {
            return false;
        }

        fwrite($file, $buffer);
        fclose($file);

        if (!$image_info = getimagesize($tmp_path)) {
            unlink($tmp_path);
            return false;
        }

        if (!($image = $this->file2res($tmp_path, $image_info[2]))) {
            unlink($tmp_path);
            return false;
        }

        unlink($tmp_path);

        $image_w = imagesx($image);
        $image_h = imagesy($image);

        if ($image_w < $image_min_w || $image_h < $image_min_h) {
            imagedestroy($image);
            return false;
        }

        if (preg_match('#^(\d++)x(\d++)$#', Config::get('auth.oauth_export_photo_trim'), $data)) {
            $image = $this->trimPhoto($image, $image_w, $image_h, $data[1], $data[2]);

            if (!$image) {
                return false;
            }

            $image_w = $data[1];
            $image_h = $data[2];
        }

        $hash = md5(implode(':', array($tmp_path, $image_w, $image_h, $photo)));

        $dir = '/images/avatars/'.substr($hash, 0, 2).'/'.substr($hash, 3, 1).'/';
        $file = substr($hash, 10, 10) . '.png';

        if (!file_exists($dir_path = ROOT_PATH . '/frontend/public/' . $dir)) {
            $oldumask = umask(0);
            mkdir($dir_path, octdec(str_pad("777", 4, '0', STR_PAD_LEFT)), true);
            umask($oldumask);
        }

        imagepng($image, ROOT_PATH . '/frontend/public/' . $dir . $file, 0);

        return $dir . $file;
    }

    protected function trimPhoto($img_old, $image_w, $image_h, $new_w, $new_h) {
        $img_new = imagecreatetruecolor($new_w, $new_h);
        imagesavealpha($img_new, true);

        $color = imageColorAllocateAlpha($img_new, 255, 255, 255, 127);
        imagefill($img_new, 0, 0, $color);

        $scale = min(
            $image_w / $new_w,
            $image_h / $new_h
        );

        $scaled_w = min($image_w, round($new_w * $scale));
        $scaled_h = min($image_h, round($new_h * $scale));

        $image_x = 0;
        $image_y = 0;

        if ($image_w > $scaled_w) {
            $image_x = (int)(($image_w - $scaled_w) / 2);
        }

        if ($image_h > $scaled_h) {
            $image_y = (int)(($image_h - $scaled_h) / 2);
        }

        imagecopyresampled (
            $img_new, $img_old,
            0, 0,
            $image_x, $image_y,
            $new_w, $new_h,
            $scaled_w, $scaled_h
        );

        return $img_new;
    }

    protected function file2res($image_path, $image_type) {
        $img = false;

        switch($image_type) {
            case 1: {
                $img = imagecreatefromgif($image_path);
                break;
            }
            
            case 2: {
                $img = imagecreatefromjpeg($image_path);
                break;
            }
            
            case 3: {
                $img = imagecreatefrompng($image_path);
                break;
            }
        }

        if(!$img) {
            return false;
        }
        
        if (!imageIsTrueColor($img)) {
            $tmp = $img;
            $w = imagesx($tmp); $h = imagesy($tmp);
            
            $img = imagecreatetruecolor($w, $h);
            imagesavealpha($img, true);
            imageCopyResampled($img, $tmp, 0, 0, 0, 0, $w, $h, $w, $h);
            imageDestroy($tmp);
        }

        return $img;
    }

    protected function unlinkAllAccount($user_id) {
        if (!$user_id) {
            return true;
        }

        $accounts = Database::from(static::$table);
        $accounts = $accounts->where('user_id', '=', $user_id)->get();

        foreach ($accounts as $account) {
            $account->user_id = 0;
            $account->save();
        }

        return true;
    }

    protected function getConfig() {
        if (!static::$prefix) {
            return false;
        }

        $config = Config::get('auth.oauth');
        if (!isset($config[static::$prefix])) {
            return false;
        }

        $config = $config[static::$prefix];

        if (!isset($config['enabled']) || !$config['enabled']) {
            return false;
        }

        return $config;
    }
}