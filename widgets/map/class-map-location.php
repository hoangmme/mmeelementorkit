<?php
namespace Ems_Elements_Share\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Turns whatever a field holds into a location Google Maps can embed without
 * an API key: "lat,lng", an address, a Google Maps link (long or
 * maps.app.goo.gl), an ACF Google Map field or an ACF Link field.
 */
class Ems_Map_Location {

    /**
     * Parse a value into array( 'query' => string, 'lat' => string, 'lng' => string ), or null.
     *
     * @param mixed $value
     * @return array|null
     */
    public static function parse( $value ) {
        if ( is_array( $value ) ) {
            // ACF Google Map field.
            if ( isset( $value['lat'], $value['lng'] ) && '' !== $value['lat'] && '' !== $value['lng'] ) {
                return self::from_coordinates( $value['lat'], $value['lng'] );
            }
            if ( ! empty( $value['address'] ) && is_string( $value['address'] ) ) {
                return self::parse( $value['address'] );
            }
            // ACF Link field.
            if ( ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
                return self::parse( $value['url'] );
            }
            return null;
        }

        if ( ! is_scalar( $value ) ) {
            return null;
        }

        $value = trim( wp_strip_all_tags( (string) $value ) );
        if ( '' === $value ) {
            return null;
        }

        // "lat,lng" (out of range pairs return null instead of being searched as an address).
        if ( preg_match( '/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/', $value, $match ) ) {
            return self::from_coordinates( $match[1], $match[2] );
        }

        if ( preg_match( '#^https?://#i', $value ) ) {
            return self::from_url( $value );
        }

        // Anything else is treated as an address / place name.
        return array( 'query' => mb_substr( $value, 0, 200 ), 'lat' => '', 'lng' => '' );
    }

    /**
     * Validate coordinates.
     *
     * @param mixed $lat
     * @param mixed $lng
     * @return array|null
     */
    private static function from_coordinates( $lat, $lng ) {
        if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) || abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
            return null;
        }
        $lat = (string) (float) $lat;
        $lng = (string) (float) $lng;
        return array( 'query' => $lat . ',' . $lng, 'lat' => $lat, 'lng' => $lng );
    }

    /**
     * Whether a host belongs to Google Maps (google.com, google.com.vn, maps.google.*).
     *
     * @param string $host
     * @return boolean
     */
    private static function is_google_host( $host ) {
        return (bool) preg_match( '/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host );
    }

    /**
     * Read a location from a Google Maps link. Other links are ignored.
     *
     * @param string $url
     * @param int    $depth Short link redirects followed so far.
     * @return array|null
     */
    private static function from_url( $url, $depth = 0 ) {
        $parts = wp_parse_url( $url );
        $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';

        // maps.app.goo.gl / goo.gl/maps short links redirect to the full link.
        if ( 'maps.app.goo.gl' === $host || ( 'goo.gl' === $host && 0 === strpos( $path, '/maps' ) ) ) {
            $resolved = $depth < 3 ? self::resolve_short_url( $url ) : '';
            return $resolved ? self::from_url( $resolved, $depth + 1 ) : null;
        }

        if ( ! self::is_google_host( $host ) || ( 0 !== strpos( $host, 'maps.' ) && false === strpos( $path, '/maps' ) ) ) {
            return null;
        }

        $decoded = rawurldecode( $url );

        // The place pin (!3d…!4d…) is more precise than the viewport centre (@lat,lng).
        if ( preg_match( '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $decoded, $match ) ) {
            return self::from_coordinates( $match[1], $match[2] );
        }
        if ( preg_match( '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $decoded, $match ) ) {
            return self::from_coordinates( $match[1], $match[2] );
        }

        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
            foreach ( array( 'q', 'query', 'll', 'daddr' ) as $param ) {
                if ( ! empty( $query[ $param ] ) && is_string( $query[ $param ] ) ) {
                    return self::parse( $query[ $param ] );
                }
            }
        }

        // /maps/place/Name/… or /maps/search/Name/…
        if ( preg_match( '#/maps/(?:place|search)/([^/@]+)#', $path, $match ) ) {
            return self::parse( str_replace( '+', ' ', rawurldecode( $match[1] ) ) );
        }

        return null;
    }

    /**
     * Follow one redirect of a Google Maps short link. Cached for 30 days.
     *
     * @param string $url
     * @return string Target url, or '' on failure.
     */
    private static function resolve_short_url( $url ) {
        $cache_key = 'mme_kit_map_' . md5( $url );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $response = wp_safe_remote_head( $url, array( 'redirection' => 0, 'timeout' => 10 ) );
        $location = is_wp_error( $response ) ? '' : wp_remote_retrieve_header( $response, 'location' );
        $location = is_string( $location ) ? $location : '';

        // Cache failures briefly so a broken link is not requested on every page view.
        set_transient( $cache_key, $location, $location ? 30 * DAY_IN_SECONDS : HOUR_IN_SECONDS );
        return $location;
    }

    /**
     * Keyless Google Maps embed url.
     *
     * @param array  $location From parse().
     * @param int    $zoom
     * @param string $type     'm' road map, 'k' satellite.
     * @param string $language e.g. 'vi'.
     * @return string
     */
    public static function embed_url( $location, $zoom = 15, $type = 'm', $language = '' ) {
        $args = array(
            'q'      => $location['query'],
            'z'      => max( 1, min( 21, (int) $zoom ) ),
            't'      => 'k' === $type ? 'k' : 'm',
            'output' => 'embed',
        );
        if ( preg_match( '/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/', $language ) ) {
            $args['hl'] = $language;
        }
        return 'https://maps.google.com/maps?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
    }

    /**
     * "Open in Google Maps" link (Maps URLs, no key needed).
     *
     * @param array $location From parse().
     * @return string
     */
    public static function open_url( $location ) {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $location['query'] );
    }
}
