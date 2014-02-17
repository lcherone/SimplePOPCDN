SimplePOPCDN
============

SimplePOPCDN is a "Origin Pull" CDN Pass-through caching class, that can be used as a stand alone script or integrated into a controller of sort as a model.
Its function is to automatically cache a resource's E.G image, CSS, js files from your main site so as to speed up loading times by distributing requests.

* @param string $origin = Host that we want to mirror static resources.
* @param string $cache_path = Path to local cache.
* @param string $fix_request = Remove a part of the request string to fix if script is sitting in a subdir.
* @param int $cache_expire = Amount of time in seconds cache is valid for. 2628000 = 1 month.

Example usage: `new SimplePOPCDN('http://server.to.mirror.com', './cache/', '/subdir', 2628000);`
