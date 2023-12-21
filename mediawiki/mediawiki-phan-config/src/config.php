<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWikiPhanConfig\MediaWikiConfigBuilder;

require_once __DIR__ . '/base-config-functions.php';

// Replace \\ by / for windows users to let exclude work correctly
$DIR = str_replace( '\\', '/', __DIR__ );

// TODO: Use \Phan\Config::projectPath()
$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../..';

$VP = getenv( 'MW_VENDOR_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_VENDOR_PATH' ) )
	: $IP;

$baseCfg = new MediaWikiConfigBuilder( $IP );
setBaseOptions( $DIR, $baseCfg );

$baseCfg = $baseCfg
	->setDirectoryList( filterDirs( [
		'includes/',
		'src/',
		'maintenance/',
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
	] ) )
	->setExcludedDirectoryList( [
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',

	] )
	->setSuppressedIssuesList( [
		// Deprecation warnings
		'PhanDeprecatedFunction',
		'PhanDeprecatedClass',
		'PhanDeprecatedClassConstant',
		'PhanDeprecatedFunctionInternal',
		'PhanDeprecatedInterface',
		'PhanDeprecatedProperty',
		'PhanDeprecatedTrait',

		// Covered by codesniffer
		'PhanUnreferencedUseNormal',
		'PhanUnreferencedUseFunction',
		'PhanUnreferencedUseConstant',
		'PhanDuplicateUseNormal',
		'PhanDuplicateUseFunction',
		'PhanDuplicateUseConstant',
		'PhanUseNormalNoEffect',
		'PhanUseNormalNamespacedNoEffect',
		'PhanUseFunctionNoEffect',
		'PhanUseConstantNoEffect',
		'PhanDeprecatedCaseInsensitiveDefine',

		// https://github.com/phan/phan/issues/3420
		'PhanAccessClassConstantInternal',
		'PhanAccessClassInternal',
		'PhanAccessConstantInternal',
		'PhanAccessMethodInternal',
		'PhanAccessPropertyInternal',

		// These are quite PHP8-specific
		'PhanParamNameIndicatingUnused',
		'PhanParamNameIndicatingUnusedInClosure',
		'PhanProvidingUnusedParameter',

		// Would probably have many false positives
		'PhanPluginMixedKeyNoKey',
	] )
	->addGlobalsWithTypes( [
		'wgContLang' => '\\Language',
		'wgParser' => '\\Parser',
		'wgTitle' => '\\Title',
		'wgMemc' => '\\BagOStuff',
		'wgUser' => '\\User',
		'wgConf' => '\\SiteConfiguration',
		'wgLang' => '\\Language',
		'wgOut' => '\\OutputPage',
		'wgRequest' => '\\WebRequest',
	] )
	->enableTaintCheck( $DIR, $VP )
	->suppressIssueTypes(
		// PHP 7.4 functionality; suppress by default until we no longer support PHP < 7.4.
		// In reality, this means when MW 1.35 is EOL, expected September 2023.
		// This will hopefully prevent some issues with backporting.
		'PhanPluginDuplicateExpressionAssignmentOperation',
	);

// BC: We're not ready to use the ConfigBuilder everywhere
return $baseCfg->make();
