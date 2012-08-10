<?php


class Tuffy_Curl_Exception extends Exception {
    // This class intentionally left blank.
}


class Tuffy_Curl {
    private $ch;
    private $status;

    public function __construct ($url = NULL, $query = NULL) {
        if ($query) {
            $url = $url . '?' . Tuffy_Util::buildQuery($query);
        }
        $this->ch = curl_init($url);
    }

    public function __destruct () {
        curl_close($this->ch);
    }

    public function __set ($name, $value) {
        $this->setOption($name, $value);
    }

    public function setOption ($option, $value) {
        if (is_string($option)) {
            $constant = 'CURLOPT_' . strtoupper($option);
            if (defined($constant)) {
                $option = constant($constant);
            } else {
                throw new Tuffy_Curl_Exception("Undefined option $option");
            }
        }
        curl_setopt($this->ch, $option, $value);
    }

    public function post ($fields) {
        $this->setOption(CURLOPT_POSTFIELDS, Tuffy::buildQuery($fields));
    }

    public function download () {
        $this->setOption(CURLOPT_RETURNTRANSFER, TRUE);
        return $this->exec();
    }

    public function downloadJSON ($assoc = TRUE) {
        $this->setOption(CURLOPT_RETURNTRANSFER, TRUE);
        $json = $this->exec();
        return json_decode($json, $assoc);
    }

    public function display () {
        $this->setOption(CURLOPT_RETURNTRANSFER, FALSE);
        return $this->exec();
    }

    public function saveTo ($path) {
        $this->setOption(CURLOPT_RETURNTRANSFER, FALSE);
        $fd = fopen($path, 'w');
        $this->setOption(CURLOPT_FILE, $fd);
        $rv = $this->exec();
        fclose($fd);
        return $rv;
    }

    public function exec () {
        $rv = curl_exec($this->ch);
        if ($rv === FALSE) {
            throw new Tuffy_Curl_Exception(curl_error($this->ch),
                                           curl_errno($this->ch));
        }
        $this->status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        return $rv;
    }

    public function getStatus () {
        return $this->status;
    }
}

