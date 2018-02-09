<?php
namespace slowcheetah\tmbotcreator;

use danog\MadelineProto\API;

class TmBotCreator
{
    const KEY_PREFIX = "session.";

    protected $settings;
    protected $phone;
    protected $logged;

    public $proto;

    public function __construct($settings = [])
    {
        $this->phone = $settings['phone'] ?? null;
        $this->settings = $settings['madeline_options'] ?? [];
    }

    /**
     * @param string $name
     * @param string $userName
     *
     * @return bool|string
     */
    public function createBot($name, $userName)
    {
        if (!$this->logged && !$this->auth()) {
            return false;
        }

        $response = $this->sendMessage('@botfather', "/newbot", true);
        if (!$response || !preg_match("/please choose a name for your bot/i", $response['message'])) {
            return false;
        }
        $response = $this->sendMessage('@botfather', $name, true);
        if (!$response || !preg_match("/choose a username for your bot/i", $response['message'])) {
            return false;
        }
        $response = $this->sendMessage('@botfather', $userName, true);
        if (!$response || !preg_match("/use this token to access the http api/i", $response['message'])) {
            return false;
        }

        preg_match("/use this token to access the http api:\n([^\n]+)/is", $response['message'], $res);

        return isset($res[1]) ? $res[1] : false;
    }

    /**
     * @return bool|API
     */
    protected function auth()
    {
        if ($this->phone && $this->logged && $this->proto) {
            return $this->proto;
        }
        if (!$this->phone) {
            return false;
        }
        $sessionName = $this->keyGen($this->phone);
        try {
            $this->proto = new API($sessionName);
            $this->logged = true;
        } catch (\Throwable $e) {
            $this->proto = new API($this->settings);
            $this->proto->phone_login($this->phone);
            $this->proto->complete_phone_login(readline('Enter the code you received: '));
            $this->proto->session = $sessionName;
            $this->proto->serialize();
            $this->logged = true;
        }

        return $this->proto;
    }

    /**
     * @param mixed  $peer
     * @param string $message
     * @param bool   $waitResponse
     *
     * @return bool|[]
     */
    protected function sendMessage($peer, $message, $waitResponse = false)
    {
        $lastMessage = false;
        $peerId = false;
        $response = $this->proto->messages->sendMessage(['peer' => $peer, 'message' => $message]);
        foreach ($response['updates'] as $update) {
            if (isset($update['_']) && $update['_'] == "updateNewMessage") {
                $peerId = $update['message']['to_id']['user_id'] ?? false;
                break;
            }
        }
        if (!$peerId) {
            echo "no have peer id\n";
            return false;
        }
        sleep(5);
        if ($waitResponse) {
            $i = 0;
            $retries = 12;
            while ($i < $retries) {
                $messages = $this->proto->messages->getHistory(['peer' => "@botfather", 'offset_id' => 0, 'offset_date' => 0, 'add_offset' => 0, 'limit' => 1, 'max_id' => 0, 'min_id' => 0, 'hash' => 0]);
                $lastMessage = $messages['messages'][0] ?? false;
                if ($lastMessage && $lastMessage['from_id'] == $peerId) {
                    break;
                }
                sleep(5);
                $i++;
            }
        }

        return ($lastMessage) ? $lastMessage : false;
    }

    /**
     * @param mixed $phone
     *
     * @return string
     */
    protected function keyGen($phone)
    {
        return TmBotCreator::KEY_PREFIX . "{$phone}";
    }
}
