Migrate to FOSHttpCacheBundle
=============================

The LiipCacheControlBundle went into maintenance only mode. It is replaced by 
the [FOSHttpCacheBundle](https://github.com/FriendsOfSymfony/FOSHttpCacheBundle).

This new bundle is a lot more flexible and cleaner, as well as better documented
and tested. It is a cleanup of the features found in the LiipCacheControlBundle
with the addition of things from [DirebitHttpCacheBundle](https://github.com/driebit/DriebitHttpCacheBundle).

This guide should help you to adapt your project. If you have questions or additions,
please use the LiipCacheControlBundle issue tracker. Pull requests on this file are
probably the only thing that will still be merged in the future ;-)

Configuration changes
---------------------

The configuration namespace changes from `liip_cache_control` to `fos_http_cache`.

### Configuration format for cache control rules changed

The rules are now under fos_http_cache.cache_control.rules, match criteria are grouped under `match`
and the headers under a `header` element, with `controls` becoming `cache_control`. For example, this
old configuration:

```yaml
liip_cache_control:
    rules:
        -
            path: ^/products.*
            controls:
                public: true
                max_age: 42
            reverse_proxy_ttl: 3600
```
 
becomes the following: 

```yaml
fos_http_cache:
    cache_control:
        rules:
            -
                match:
                    path: ^/products.*
                headers:
                    cache_control:
                        public: true
                        max_age: 42
                    reverse_proxy_ttl: 3600
```

Note that the FOSHttpCacheBundle only sets cache headers if the response has a
"safe" status, that is one of 200, 203, 300, 301, 302, 404, 410. You can configure
additional_cacheable_status to add more status, or use a match_response expression
to operate on the Response object.

### flash_message_listener becomes flash_message

The name of this configuration section changed.
        
Authorization Listener becomes User Context Subscriber
------------------------------------------------------

Instead of simply aborting HEAD requests, the FOSHttpCacheBundle can provide a "context token",
e.g. a hash over the *roles* of a user. With this, it becomes possible to share the cache between
different users sharing the same role. If you where using the authorization listener, you want to
study the [user context](http://foshttpcachebundle.readthedocs.org/en/latest/event-subscribers/user-context.html)
section of the new documentation.

Varnish client becomes cache manager
------------------------------------

The cache manager abstracts from the caching proxy. Currently supported are varnish and nginx, and we
hope to get support for the symfony built in cache as well at some point. Instead of the mess with choosing
between BAN and PURGE, the new bundle supports both of them, along with REFRESH.

The configuration changes:

```yaml
    varnish:
        purge_instruction: ban
        ips:  "%varnish_ips%"
        port: "%varnish_port%"
        host: "%varnish_hostname%"
        headers: "%varnish_headers%"
```

Becomes:

```yaml
    proxy_client:
        varnish:
            servers: "%varnish_ips%"
            base_url: "%varnish_hostname%"
            guzzle_client: acme.varnish.guzzle.client
```

The ip and port are combined in the `server` field. This means that you need to 
append the port on each IP: [1.2.3.4:8080, 5.6.7.8:8080]. `host` becomes `base_url`
and may contain a path prefix if needed.

Extra `headers` are no longer supported, but you get more flexibility by 
supplying your guzzle client service if the default client is not good.

### Commands

Instead of the `liip:cache-control:varnish:invalidate` command, you can now use 

* fos:httpcache:invalidate:path
* fos:httpcache:invalidate:regex
* fos:httpcache:invalidate:tag
* fos:httpcache:refresh:path

The commands are configured as services, allowing you to reuse them (with symfony 2.4 or later)
should you need to work with several caching proxies.

General Cleanup in your Project
-------------------------------

Search your codebase for mentions of `CacheControlBundle` and check what classes you are using
and how to replace them. Search for `liip_cache_control` and check what services you are using
and how to replace them.
