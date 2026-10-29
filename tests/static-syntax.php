<?php

$root     = dirname( __DIR__ );
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$failed   = false;

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$contents = file_get_contents( $file->getPathname() );
	try {
		token_get_all( $contents, TOKEN_PARSE );
		echo "PASS {$file->getPathname()}\n";
	} catch ( ParseError $error ) {
		$failed = true;
		echo "FAIL {$file->getPathname()}: {$error->getMessage()}\n";
	}
}

exit( $failed ? 1 : 0 );
