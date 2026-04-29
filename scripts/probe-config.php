<?php
/**
 * probe-config.php - print platform config values for the smoke test.
 *
 * Runs as a maintenance script so it gets the full MediaWiki bootstrap
 * (DefaultSettings + LocalSettings + extension registration) without
 * having to go through the HTTP API, which is gated behind read
 * permissions on a private wiki.
 *
 * Output is line-oriented `KEY=value` so the smoke shell script can
 * grep + cut without pulling in a JSON parser. Lists are
 * comma-separated.
 */

require_once '/var/www/html/maintenance/Maintenance.php';

class LabkiProbeConfig extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Print platform config values for smoke testing.' );
    }

    public function execute() {
        global $wgDefaultSkin, $wgFileExtensions;

        echo "DEFAULT_SKIN={$wgDefaultSkin}\n";
        echo 'FILE_EXTENSIONS=' . implode( ',', $wgFileExtensions ?? [] ) . "\n";

        $factory = MediaWiki\MediaWikiServices::getInstance()->getSkinFactory();
        if ( method_exists( $factory, 'getInstalledSkins' ) ) {
            $skinCodes = array_keys( $factory->getInstalledSkins() );
        } else {
            $skinCodes = array_keys( $GLOBALS['wgValidSkinNames'] ?? [] );
        }
        echo 'SKINS=' . implode( ',', $skinCodes ) . "\n";

        $extNames = array_keys( ExtensionRegistry::getInstance()->getAllThings() );
        echo 'EXTENSIONS=' . implode( ',', $extNames ) . "\n";
    }
}

$maintClass = LabkiProbeConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
