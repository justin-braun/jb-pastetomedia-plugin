/**
 * JB Paste to Media: block editor integration.
 *
 * Core already turns a pasted image into an Image block at the cursor and uploads it
 * through the block editor's `mediaUpload` setting. We wrap that setting: uploads that
 * come from a paste (detected by a capture-phase listener) are renamed, converted and
 * tagged first; every other upload (drag & drop, Upload button) passes through untouched.
 */
( function ( wp, jbptm ) {
	'use strict';

	if ( ! wp || ! wp.data || ! jbptm ) {
		return;
	}

	var STORE = 'core/block-editor';
	var WINDOW_MS = 5000;
	var pending = []; // { time, size, type } for each image file in a recent paste.
	var watchedDocs = typeof WeakSet === 'function' ? new WeakSet() : null;

	function onPaste( event ) {
		// The Classic block handles its own pastes through the TinyMCE plugin.
		if ( event.target && event.target.closest && event.target.closest( '.mce-content-body, .wp-block-freeform' ) ) {
			return;
		}
		if ( ! jbptm.isImagePaste( event.clipboardData ) ) {
			return;
		}
		var now = Date.now();
		pending = pending.filter( function ( p ) { return now - p.time < WINDOW_MS; } );
		jbptm.imageFiles( event.clipboardData ).forEach( function ( f ) {
			pending.push( { time: now, size: f.size, type: f.type } );
		} );
	}

	/** Consume the pending paste marker that matches this file, if any. */
	function takeMarker( file ) {
		var now = Date.now();
		pending = pending.filter( function ( p ) { return now - p.time < WINDOW_MS; } );
		for ( var i = 0; i < pending.length; i++ ) {
			if ( pending[ i ].size === file.size && pending[ i ].type === file.type ) {
				pending.splice( i, 1 );
				return true;
			}
		}
		return false;
	}

	function watch( doc ) {
		if ( ! doc || ( watchedDocs && watchedDocs.has( doc ) ) || doc.__jbptm ) {
			return;
		}
		doc.addEventListener( 'paste', onPaste, true );
		if ( watchedDocs ) {
			watchedDocs.add( doc );
		} else {
			doc.__jbptm = true;
		}
	}

	function watchCanvas() {
		var frames = document.querySelectorAll( 'iframe[name="editor-canvas"]' );
		Array.prototype.forEach.call( frames, function ( frame ) {
			try {
				watch( frame.contentDocument );
			} catch ( e ) {}
		} );
	}

	/** Remove Image blocks still showing the blob of a cancelled upload. */
	function removeBlobBlocks( files ) {
		var select = wp.data.select( STORE );
		var getBlob = wp.blob && wp.blob.getBlobByURL;
		var ids = [];
		( function walk( blocks ) {
			blocks.forEach( function ( b ) {
				var url = b.attributes && ( b.attributes.blob || b.attributes.url );
				if ( b.name === 'core/image' && url && /^blob:/.test( url ) && ! b.attributes.id ) {
					var blob = getBlob ? getBlob( url ) : null;
					if ( ! blob || files.indexOf( blob ) !== -1 ) {
						ids.push( b.clientId );
					}
				}
				if ( b.innerBlocks && b.innerBlocks.length ) {
					walk( b.innerBlocks );
				}
			} );
		} )( select.getBlocks() );
		if ( ids.length ) {
			wp.data.dispatch( STORE ).removeBlocks( ids, false );
		}
	}

	function wrap( original ) {
		var wrapped = function ( options ) {
			var files = Array.prototype.slice.call( ( options && options.filesList ) || [] );
			var ours = files.filter( function ( f ) { return f && /^image\//.test( f.type ) && takeMarker( f ); } );
			if ( ! ours.length ) {
				return original( options );
			}

			var others = files.filter( function ( f ) { return ours.indexOf( f ) === -1; } );
			if ( others.length ) {
				original( Object.assign( {}, options, { filesList: others } ) );
			}

			// Process sequentially so prompt dialogs appear one at a time.
			ours.reduce( function ( chain, file ) {
				return chain.then( function () {
					return jbptm.process( file ).then( function ( result ) {
						if ( ! result ) {
							removeBlobBlocks( [ file ] );
							return;
						}
						var extra = {};
						Object.keys( result.meta ).forEach( function ( k ) {
							if ( result.meta[ k ] !== '' ) {
								extra[ k ] = result.meta[ k ];
							}
						} );
						return original( Object.assign( {}, options, {
							filesList: [ result.file ],
							additionalData: Object.assign( {}, options.additionalData || {}, extra )
						} ) );
					} );
				} ).catch( function ( err ) {
					if ( options.onError ) {
						options.onError( { message: ( err && err.message ) || jbptm.__( 'Paste upload failed.', 'jb-paste-to-media' ), file: file } );
					}
				} );
			}, Promise.resolve() );
		};
		wrapped.__jbptm = true;
		return wrapped;
	}

	function ensureWrapped() {
		var settings = wp.data.select( STORE ).getSettings();
		if ( settings && typeof settings.mediaUpload === 'function' && ! settings.mediaUpload.__jbptm ) {
			wp.data.dispatch( STORE ).updateSettings( { mediaUpload: wrap( settings.mediaUpload ) } );
		}
	}

	wp.domReady( function () {
		watch( document );
		watchCanvas();
		ensureWrapped();
		// The editor may reset settings or swap the canvas iframe; re-apply when it does.
		wp.data.subscribe( function () {
			ensureWrapped();
			watchCanvas();
		} );
	} );
} )( window.wp, window.jbptm );
