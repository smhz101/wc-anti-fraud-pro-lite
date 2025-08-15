<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Country/region validation presets.
 * phone/postal are PHP-style regex strings with delimiters.
 * Add more as needed; keys map to <option value=""> in the UI.
 */
function wca_presets() {
	return array(
		'generic' => array(
			'label'    => __( 'Generic (international)', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^\+?[0-9()\-\s]{7,20}$/',
			'postal'   => '/^[A-Za-z0-9\-\s]{3,12}$/',
			'examples' => array(
				'phone'  => array(
					'good' => array( '+1 202 555 0133', '(020) 7946 0991', '+44-7700-900123' ),
					'bad'  => array( 'abc-123', '++--', '123' ),
				),
				'postal' => array(
					'good' => array( '90210', 'H2B 2Y5', '75008', '10115' ),
					'bad'  => array( '***', 'A', '1234567890123456' ),
				),
			),
		),
		'us'      => array(
			'label'    => __( 'United States', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^(?:\+1\s?)?(?:\(?\d{3}\)?[\s.-]?)\d{3}[\s.-]?\d{4}$/',
			'postal'   => '/^\d{5}(?:-\d{4})?$/',
			'examples' => array(
				'phone'  => array(
					'good' => array( '202-555-0100', '(415) 555-2671', '+1 212 555 0199' ),
					'bad'  => array( '020 7946 0991', '555-01' ),
				),
				'postal' => array(
					'good' => array( '10001', '94107-1234' ),
					'bad'  => array( 'SW1A 1AA', 'ABCDE' ),
				),
			),
		),
		'uk'      => array(
			'label'    => __( 'United Kingdom', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$|^(\+44\s?1\d{3}|\(?01\d{3}\)?)\s?\d{3}\s?\d{3}$/',
			'postal'   => '/^([A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2})$/i',
			'examples' => array(
				'phone'  => array(
					'good' => array( '07700 900123', '+44 20 7946 0991' ),
					'bad'  => array( '202-555-0199', '07 12' ),
				),
				'postal' => array(
					'good' => array( 'SW1A 1AA', 'EC1A 1BB' ),
					'bad'  => array( '12345', 'ABCDE-1234' ),
				),
			),
		),
		'ca'      => array(
			'label'    => __( 'Canada', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^(?:\+1\s?)?(?:\(?\d{3}\)?[\s.-]?)\d{3}[\s.-]?\d{4}$/',
			'postal'   => '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][\s\-]?\d[ABCEGHJ-NPRSTV-Z]\d$/i',
			'examples' => array(
				'phone'  => array(
					'good' => array( '416-555-0123', '+1 (604) 555-0199' ),
					'bad'  => array( '020 7946 0991' ),
				),
				'postal' => array(
					'good' => array( 'H2B 2Y5', 'K1A 0B1' ),
					'bad'  => array( '12345', 'SW1A 1AA' ),
				),
			),
		),
		'au'      => array(
			'label'    => __( 'Australia', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^(?:\+61\s?)?(?:0?[2-478])\d{8}$/',
			'postal'   => '/^\d{4}$/',
			'examples' => array(
				'phone'  => array(
					'good' => array( '+61 2 9374 4000', '0432 123 456' ),
					'bad'  => array( '202-555-0199' ),
				),
				'postal' => array(
					'good' => array( '2000', '3001' ),
					'bad'  => array( 'SW1A 1AA', '123456' ),
				),
			),
		),
		'eu'      => array(
			'label'    => __( 'European Union (common)', 'wc-anti-fraud-pro-lite' ),
			'phone'    => '/^\+?[0-9()\-\s]{8,20}$/',
			'postal'   => '/^[0-9A-Z\- ]{4,10}$/i',
			'examples' => array(
				'phone'  => array(
					'good' => array( '+33 1 42 68 53 00', '+49 30 901820' ),
					'bad'  => array( 'abc-123' ),
				),
				'postal' => array(
					'good' => array( '75008', '10115', '00144' ),
					'bad'  => array( 'ABCDE-1234' ),
				),
			),
		),
	);
}
