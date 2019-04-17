<?php
/**
 * Defines the class Table and it's children
 *
 * @package    (p2pecp\classes\)
 */

defined( 'ABSPATH' ) || die( 'Bitcoin is for all!' );

/**
 * Table
 */
abstract class Table {

	/**
	 * Returns the bitcoin variant the child table class implements
	 */
	abstract public static function get_bitcoin_variant();

	/**
	 * Schema version for the table
	 *
	 * @return float
	 */
	abstract public static function get_schema_version();

	/**
	 * Return the query that creates the database if one does not exist already with the same name
	 *
	 * @param  string $btc_addresses_table_name name of the table to be created.
	 * @return string                           query that creates the required table.
	 */
	abstract public static function get_query( $btc_addresses_table_name );

	/**
	 * Return the table name to be used by the child class
	 *
	 * @return string the table name based on the bitcoin variant set by the child class
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'p2pecp_addresses_' . static::get_bitcoin_variant();
	}

	/**
	 * Create the table(s) needed for the operation of the child variant
	 *
	 * @return void nothing is returned.
	 */
	public static function create_database_tables() {
		global $wpdb;

		$ecp_settings         = ecp__get_settings();
		$must_update_settings = false;

		$btc_addresses_table_name = static::get_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$btc_addresses_table_name'" ) != $btc_addresses_table_name ) {
			$b_first_time = true;
		} else {
			$b_first_time = false;
		}

		$query = static::get_query( $btc_addresses_table_name );
		$wpdb->query( $query );
		// ----------------------------------------------------------
		// upgrade ecp_btc_addresses table, add additional indexes
		if ( ! $b_first_time ) {
			$version = floatval( $ecp_settings['database_schema_version'][ static::get_bitcoin_variant() ] );
			// For future updates.
		} else {
			if ( ! is_array( $ecp_settings['database_schema_version'] ) ) {
				$ecp_settings['database_schema_version'] = array();
			}
			$ecp_settings['database_schema_version'][ static::get_bitcoin_variant() ] = static::get_schema_version();
		}
	}

	/**
	 * Delete the databases upon created
	 *
	 * @return void
	 */
	public static function delete_database_tables() {
		global $wpdb;

		$btc_addresses_table_name = static::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS `$btc_addresses_table_name`" );
	}
}
/**
 * Class implementing BCH Tables
 */
class TableBCH extends Table {

	/**
	 * Variant this table represents
	 *
	 * @return string the bitcoin variant in this case bch
	 */
	public static function get_bitcoin_variant() {
		return 'bch';
	}

	/**
	 * Schema version, this is useful for future changes
	 *
	 * @return int version of the schema
	 */
	public static function get_schema_version() {
		return 1.0;
	}

	/**
	 * Query string that allows the creation of the table needed
	 * bch includes bch_cashaddr
	 *
	 * @param  string $btc_addresses_table_name name of the tabel to be created.
	 * @return strign                           sting containing the SQL
	 */
	public static function get_query( $btc_addresses_table_name ) {
		return "CREATE TABLE IF NOT EXISTS `$btc_addresses_table_name` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `btc_address` char(36) NOT NULL,
            `bch_cashaddr` char(80),
            `origin_id` char(128) NOT NULL DEFAULT '',
            `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
            `status` char(16)  NOT NULL DEFAULT 'unknown',
            `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
            `address_meta` MEDIUMBLOB NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `btc_address` (`btc_address`),
            UNIQUE KEY `bch_cashaddr` (`bch_cashaddr`),
            KEY `index_in_wallet` (`index_in_wallet`),
            KEY `origin_id` (`origin_id`),
            KEY `status` (`status`)
            );";
	}
}

/**
 * Class implementing BSV Tables
 */
class TableBSV extends Table {

	/**
	 * Variant this table represents
	 *
	 * @return string the bitcoin variant in this case bsv
	 */
	public static function get_bitcoin_variant() {
		return 'bsv';
	}

	/**
	 * Schema version, this is useful for future changes
	 *
	 * @return int version of the schema
	 */
	public static function get_schema_version() {
		return 1.0;
	}

	/**
	 * Query string that allows the creation of the table needed
	 *
	 * @param  string $btc_addresses_table_name name of the tabel to be created.
	 * @return strign                           sting containing the SQL
	 */
	public static function get_query( $btc_addresses_table_name ) {
		return "CREATE TABLE IF NOT EXISTS `$btc_addresses_table_name` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `btc_address` char(36) NOT NULL,
            `origin_id` char(128) NOT NULL DEFAULT '',
            `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
            `status` char(16)  NOT NULL DEFAULT 'unknown',
            `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
            `address_meta` MEDIUMBLOB NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `btc_address` (`btc_address`),
            KEY `index_in_wallet` (`index_in_wallet`),
            KEY `origin_id` (`origin_id`),
            KEY `status` (`status`)
            );";
	}
}
