<?php

/*
 * The site sits behind Cloudflare, so every request reaches nginx from a Cloudflare edge and
 * PHP sees that edge as the visitor. Rate limits then key on the edge: one bucket shared by
 * everyone in the same city, and a login throttle an attacker sidesteps by landing on another
 * edge. Trusting these ranges (and only these) lets Laravel read the real client address from
 * X-Forwarded-For. A request from anywhere else — a scanner hitting the origin directly — keeps
 * its own address, and whatever X-Forwarded-For it forges is ignored.
 *
 * Source: https://www.cloudflare.com/ips-v4 and /ips-v6, fetched 2026-09-26. They change rarely;
 * refresh from those two URLs if Cloudflare announces new ranges.
 */
return [
    'ranges' => [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ],
];
