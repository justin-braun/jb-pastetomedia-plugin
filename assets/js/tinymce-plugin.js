/**
 * JB Paste to Media: TinyMCE (Classic editor / Classic block) integration.
 */
/* global tinymce */
( function () {
	'use strict';

	if ( typeof tinymce === 'undefined' ) {
		return;
	}

	var counter = 0;

	function esc( str ) {
		return String( str || '' )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function buildHtml( media, s ) {
		var size = s.classic_size || 'large';
		var sizes = ( media.media_details && media.media_details.sizes ) || {};
		var chosen = sizes[ size ] || sizes.full || {};
		var src = chosen.source_url || media.source_url;
		var width = chosen.width || ( media.media_details && media.media_details.width ) || '';
		var height = chosen.height || ( media.media_details && media.media_details.height ) || '';
		var align = 'align' + ( s.classic_align || 'none' );
		var sizeClass = 'size-' + ( sizes[ size ] ? size : 'full' );

		var html = '<img src="' + esc( src ) + '" alt="' + esc( media.alt_text ) + '"' +
			( width ? ' width="' + esc( width ) + '"' : '' ) +
			( height ? ' height="' + esc( height ) + '"' : '' ) +
			' class="' + align + ' ' + sizeClass + ' wp-image-' + media.id + '" />';

		if ( s.classic_link === 'file' ) {
			html = '<a href="' + esc( media.source_url ) + '">' + html + '</a>';
		} else if ( s.classic_link === 'attachment' && media.link ) {
			html = '<a href="' + esc( media.link ) + '">' + html + '</a>';
		}

		var caption = media.caption && media.caption.raw;
		if ( caption ) {
			html = '[caption id="attachment_' + media.id + '" align="' + align + '"' +
				( width ? ' width="' + esc( width ) + '"' : '' ) + ']' + html + ' ' + esc( caption ) + '[/caption]';
		}
		return html;
	}

	tinymce.PluginManager.add( 'jbptm', function ( editor ) {
		var jbptm = window.jbptm;
		if ( ! jbptm || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		function handle( file ) {
			var id = 'jbptm-' + Date.now() + '-' + ( ++counter );
			var blobUrl = URL.createObjectURL( file );
			editor.insertContent( '<img src="' + blobUrl + '" class="jbptm-uploading" data-jbptm-id="' + id + '" alt="" />' );

			function placeholder() {
				return editor.dom.select( 'img[data-jbptm-id="' + id + '"]' )[ 0 ];
			}
			function done( html ) {
				var el = placeholder();
				if ( el ) {
					if ( html ) {
						editor.selection.select( el );
						editor.selection.setContent( html );
					} else {
						editor.dom.remove( el );
					}
				}
				URL.revokeObjectURL( blobUrl );
				editor.undoManager.add();
				editor.fire( 'change' );
			}

			return jbptm.process( file ).then( function ( result ) {
				if ( ! result ) {
					return done( '' );
				}
				var postId = jbptm.context().post_id;
				return jbptm.upload( result.file, result.meta, postId ).then( function ( media ) {
					done( buildHtml( media, jbptm.settings.image || {} ) );
				} );
			} ).catch( function ( err ) {
				done( '' );
				var msg = ( err && err.message ) || jbptm.__( 'Paste upload failed.', 'jb-paste-to-media' );
				if ( editor.notificationManager ) {
					editor.notificationManager.open( { text: msg, type: 'error', timeout: 6000 } );
				} else {
					window.alert( msg ); // eslint-disable-line no-alert
				}
			} );
		}

		editor.on( 'paste', function ( event ) {
			var dt = event.clipboardData || ( event.originalEvent && event.originalEvent.clipboardData );
			if ( ! jbptm.isImagePaste( dt ) ) {
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation && event.stopImmediatePropagation();
			var files = jbptm.imageFiles( dt );
			files.reduce( function ( chain, file ) {
				return chain.then( function () { return handle( file ); } );
			}, Promise.resolve() );
			return false;
		}, true );

		// Never save a placeholder into the post content.
		editor.on( 'GetContent', function ( e ) {
			if ( e.content && e.content.indexOf( 'jbptm-uploading' ) !== -1 ) {
				e.content = e.content.replace( /<img[^>]*class="jbptm-uploading"[^>]*>/g, '' );
			}
		} );
	} );
} )();
