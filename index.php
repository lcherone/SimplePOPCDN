<?php
/**
 * A Simple PHP "Origin Pull" CDN Passthrough caching class,
 *  which enables you to create a simple CDN for automatic 
 *  mirroring of static content on a faster, lesser loaded server.
 * 
 * Usage: new SimplePOPCDN('http://origin.com', './cache/', '/subdir', 2592000);
 * @version 1.0
 * @author Lawrence Cherone <lawrence@cherone.co.uk>
 * @see http://cherone.co.uk
 *
 */
//new SimplePOPCDN('http://cherone.co.uk', './cache/');

class SimplePOPCDN {
    /**
	 * Constructor, will set up the request and call initialize()
	 *
	 * @param string $origin = Host that we want to mirror resources
	 * @param string $cache_path = Path to cache 
	 * @param string $fix_request = Remove a part of the request string to fix if script is sitting in a subdir
	 * @param int $cache_expire = Amount of time in seconds cache is valid for. 2628000 = 1 month
	 */
    function __construct($origin=null, $cache_path=null, $fix_request=null, $cache_expire=2628000)
    {
        $this->origin       = $origin;
        $this->request      = ($fix_request !== null) ? str_replace($fix_request, '', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];
        $this->request_part = parse_url($this->request);
        $this->request_info = pathinfo($this->request_part['path']);
        $this->cache_path   = $cache_path;
        $this->cache_expire = $cache_expire;
        $this->cache_name   = sha1($this->request);
        $this->setup_request();
        $this->initialize();
    }

    private function initialize()
    {
        // Setup Gzip based on client accept header
        ob_clean();
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            ob_start("ob_gzhandler");
        } else {
            ob_start();
        }
        ob_implicit_flush(0);

        // Check local cache for file
        if (file_exists($this->cache_full)) {
            // Set resource last modified time
            $this->modified = filemtime($this->cache_full);

            // Check client cache if found send 304
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $this->modified)) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            } else {
                // Send responce headers
                header('Pragma: public');
                header("Cache-Control: max-age={$this->cache_expire}");
                header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $this->cache_expire));
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $this->modified));
                header("Content-Type: {$this->request_info['mime']}");
                header('X-Content-Type-Options: nosniff');
                header('X-XSS-Protection: 1; mode=block');
                header('Server: CDNServer');
                header('X-Powered-By: SimplePOPCDN');
                // Stream file
                set_time_limit(0);
                $h = gzopen($this->cache_full,'rb');
                while ($line = gzgets($h, 4096)){
                    echo $line;
                }
                gzclose($h);
            }
        } else {
            // HEAD request - To verify the origin resource exists
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $this->origin.$this->request,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FAILONERROR => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true
            ));

            // Origin remote file found, lets grab it
            if (curl_exec($ch) !== false) {
                $fp = fopen($this->cache_full, 'a+b');
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    // Empty *possible* contents
                    ftruncate($fp, 0);
                    rewind($fp);

                    // HTTP GET request - write directly to the file
                    $ch2 = curl_init();
                    curl_setopt_array($ch2, array(
                        CURLOPT_URL => $this->origin.$this->request,
                        CURLOPT_TIMEOUT => 5,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_FAILONERROR => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_BINARYTRANSFER => true,
                        CURLOPT_HEADER => false,
                        CURLOPT_FILE => $fp
                    ));

                    // Transfer failed
                    if (curl_exec($ch2) === false) {
                        ftruncate($fp, 0);
                    }
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    curl_close($ch2);
                }
                fclose($fp);
                // Issue a 307 Temporary Redirect
                header('Location: '.$this->origin.$this->request, TRUE, 307);
            } else {
                // File not found, issue 404
                $this->error($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
            }
            // Finished
            curl_close($ch);
        }

        // Cont.. Gzip header check
        if (headers_sent()) {
            $encoding = false;
        } elseif (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) {
            $encoding = 'x-gzip';
        } elseif (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false) {
            $encoding = 'gzip';
        } else {
            $encoding = false;
        }

        // Finally output the buffer
        if ($encoding) {
            $contents = ob_get_contents();
            ob_end_clean();
            header('Content-Encoding: '.$encoding);
            print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
            $size = strlen($contents);
            echo substr(gzcompress($contents, 9), 0, $size);
            exit();
        } else {
            ob_end_flush();
            exit();
        }
    }

    private function setup_request()
    {
        if (!isset($this->request_info['extension'])) {
            $this->request_info['extension'] = null;
        }
        switch ($this->request_info['extension']) {
            case 'gif' : $this->request_info['mime'] = 'image/gif';	break;
            case 'jpg' : $this->request_info['mime'] = 'image/jpeg'; break;
            case 'png' : $this->request_info['mime'] = 'image/png'; break;
            case 'ico' : $this->request_info['mime'] = 'image/x-icon'; break;
            case 'js'  : $this->request_info['mime'] = 'application/javascript;charset=utf-8'; break;
            case 'css' : $this->request_info['mime'] = 'text/css;charset=utf-8'; break;
            case 'xml' : $this->request_info['mime'] = 'text/xml;charset=utf-8'; break;
            case 'json': $this->request_info['mime'] = 'application/json;charset=utf-8'; break;
            case 'txt' : $this->request_info['mime'] = 'text/plain;charset=utf-8'; break;
            case 'otf' : $this->request_info['mime'] = 'font/otf'; break;
            case 'woff' : $this->request_info['mime'] = 'font/woff'; break;
            default :
            if (empty($this->request_info['extension'])) {
                $this->error($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden');
            } else {
                // extension is not supported, issue *415 Unsupported Media Type*
                $this->error($_SERVER['SERVER_PROTOCOL'].' 415 Unsupported Media Type');
            }
        }
        $this->cache_full = $this->cache_path.$this->cache_name.'.'.$this->request_info['extension'];
    }

    private function error($header = "HTTP/1.1 404 Not Found", $message = "")
    {
        header($header);
        header('Content-Type: text/html');
        header('Cache-Control: private');
        exit('<!DOCTYPE HTML><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>'.$this->origin.' CDN | '.htmlspecialchars($header).'</title></head><body><h1><a href="'.$this->origin.'">'.$this->origin.'</a> CDN - '.htmlspecialchars($header).'</h1><p>'.$message.'</p></body></html>');
    }
}
?>
