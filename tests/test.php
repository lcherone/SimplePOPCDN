<?php
include '../index.php';

class SimplePOPCDNTest extends PHPUnit_Framework_TestCase {
 
    public function test()
    {
        $test = new SimplePOPCDN('http://server.to.mirror.com', './cache/', '/subdir', 2628000);
        $this->assertTrue(true);
    }
 
}
