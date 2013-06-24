SimplePOPCDN
============

A Simple PHP "Origin Pull" CDN Passthrough caching class, can be used as a stand alone script or intergrated into a controller of sort as a model.


* @param string $origin = Host that we want to mirror resources
* @param string $cache_path = Path to cache
* @param string $fix_request = Remove a part of the request string to fix if script is sitting in a subdir
* @param int $cache_expire = Amount of time in seconds cache is valid for. 2628000 = 1 month

Usage: `new SimplePOPCDN('http://server.to.mirror.com', './cache/', '/subdir', 259200);`
