/**
 * JB Paste to Media: shared helpers (templating, conversion, dialog, upload).
 *
 * Exposes window.jbptm.
 */
( function ( window, document ) {
	'use strict';

	var data = window.jbptmData || { mode: 'silent', image: {}, ctx: {} };
	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) { return s; };

	var EXT = {
		'image/png': 'png',
		'image/jpeg': 'jpg',
		'image/webp': 'webp',
		'image/gif': 'gif',
		'image/avif': 'avif'
	};

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function slugify( str ) {
		return String( str || '' )
			.normalize( 'NFKD' )
			.replace( /[̀-ͯ]/g, '' )
			.toLowerCase()
			.replace( /[^a-z0-9\-_]+/g, '-' )
			.replace( /-{2,}/g, '-' )
			.replace( /^[-_]+|[-_]+$/g, '' );
	}

	/**
	 * Current context: localized values, refreshed from the block editor when present
	 * so an unsaved title or slug is still used.
	 */
	function context() {
		var ctx = Object.assign( {}, data.ctx );
		try {
			var editor = window.wp && window.wp.data && window.wp.data.select( 'core/editor' );
			if ( editor && editor.getCurrentPostId ) {
				ctx.post_id = editor.getCurrentPostId() || ctx.post_id;
				ctx.post_title = editor.getEditedPostAttribute( 'title' ) || '';
				ctx.post_slug = editor.getEditedPostAttribute( 'slug' ) || '';
				ctx.post_type = editor.getCurrentPostType() || ctx.post_type;
			}
		} catch ( e ) {}
		if ( ! ctx.post_title && window.document.getElementById( 'title' ) ) {
			ctx.post_title = window.document.getElementById( 'title' ).value;
		}
		if ( ! ctx.post_slug ) {
			ctx.post_slug = slugify( ctx.post_title );
		}
		return ctx;
	}

	function render( template, ctx ) {
		var d = new Date();
		var map = {
			post_id: ctx.post_id || '',
			post_title: ctx.post_title || '',
			post_slug: ctx.post_slug || '',
			post_type: ctx.post_type || '',
			user: ctx.user || '',
			site: ctx.site || '',
			date: d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ),
			time: pad( d.getHours() ) + pad( d.getMinutes() ) + pad( d.getSeconds() ),
			year: String( d.getFullYear() ),
			month: pad( d.getMonth() + 1 ),
			day: pad( d.getDate() ),
			rand: Math.random().toString( 36 ).slice( 2, 8 )
		};
		return String( template || '' ).replace( /\{([a-z_]+)\}/g, function ( m, key ) {
			return Object.prototype.hasOwnProperty.call( map, key ) ? String( map[ key ] ) : m;
		} ).trim();
	}

	/** Image files on a clipboard/drag DataTransfer. */
	function imageFiles( dt ) {
		var out = [];
		if ( ! dt ) {
			return out;
		}
		var files = dt.files && dt.files.length ? dt.files : [];
		Array.prototype.forEach.call( files, function ( f ) {
			if ( f && /^image\//.test( f.type ) ) {
				out.push( f );
			}
		} );
		if ( ! out.length && dt.items ) {
			Array.prototype.forEach.call( dt.items, function ( item ) {
				if ( item.kind === 'file' && /^image\//.test( item.type ) ) {
					var f = item.getAsFile();
					if ( f ) {
						out.push( f );
					}
				}
			} );
		}
		return out;
	}

	/**
	 * Should we take over this paste? Only when it carries images and no real text
	 * (copying from Word or a web page brings text too; core handles that).
	 */
	function isImagePaste( dt ) {
		if ( ! imageFiles( dt ).length ) {
			return false;
		}
		var text = '';
		try {
			text = dt.getData( 'text/plain' ) || '';
		} catch ( e ) {}
		return text.trim() === '';
	}

	function loadImage( file ) {
		return new Promise( function ( resolve, reject ) {
			var url = URL.createObjectURL( file );
			var img = new Image();
			img.onload = function () {
				resolve( { img: img, url: url } );
			};
			img.onerror = function () {
				URL.revokeObjectURL( url );
				reject( new Error( 'load' ) );
			};
			img.src = url;
		} );
	}

	/** Convert and/or scale in the browser according to settings. Resolves to a Blob. */
	function convert( file ) {
		var s = data.image || {};
		var format = s.format || 'original';
		var maxW = parseInt( s.max_width, 10 ) || 0;
		var quality = Math.max( 1, Math.min( 100, parseInt( s.quality, 10 ) || 82 ) ) / 100;

		// Never re-encode GIFs (animation would be lost) or unknown types.
		if ( file.type === 'image/gif' || ! EXT[ file.type ] ) {
			return Promise.resolve( file );
		}
		var target = format === 'jpeg' ? 'image/jpeg' : format === 'webp' ? 'image/webp' : file.type;

		return loadImage( file ).then( function ( r ) {
			var w = r.img.naturalWidth;
			var h = r.img.naturalHeight;
			var scale = maxW && w > maxW ? maxW / w : 1;
			if ( scale === 1 && target === file.type ) {
				URL.revokeObjectURL( r.url );
				return file;
			}
			var canvas = document.createElement( 'canvas' );
			canvas.width = Math.round( w * scale );
			canvas.height = Math.round( h * scale );
			var c2d = canvas.getContext( '2d' );
			if ( target === 'image/jpeg' ) {
				c2d.fillStyle = '#fff'; // JPEG has no transparency.
				c2d.fillRect( 0, 0, canvas.width, canvas.height );
			}
			c2d.drawImage( r.img, 0, 0, canvas.width, canvas.height );
			URL.revokeObjectURL( r.url );
			return new Promise( function ( resolve ) {
				canvas.toBlob( function ( blob ) {
					// Browser may not support the target (e.g. WebP); keep original then.
					resolve( blob && blob.type === target ? blob : file );
				}, target, quality );
			} );
		} ).catch( function () {
			return file;
		} );
	}

	function field( label, name, value ) {
		var wrap = document.createElement( 'label' );
		wrap.className = 'jbptm-field';
		var span = document.createElement( 'span' );
		span.textContent = label;
		var input = document.createElement( 'input' );
		input.type = 'text';
		input.name = name;
		input.value = value || '';
		wrap.appendChild( span );
		wrap.appendChild( input );
		return wrap;
	}

	/** Modal to review values. Resolves to the edited values, or null on cancel. */
	function dialog( blob, values, ext ) {
		return new Promise( function ( resolve ) {
			var previous = document.activeElement;
			var overlay = document.createElement( 'div' );
			overlay.className = 'jbptm-overlay';
			var box = document.createElement( 'form' );
			box.className = 'jbptm-dialog';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );
			box.setAttribute( 'aria-label', __( 'Pasted image', 'jb-paste-to-media' ) );

			var h = document.createElement( 'h2' );
			h.textContent = __( 'Pasted image', 'jb-paste-to-media' );
			box.appendChild( h );

			var url = URL.createObjectURL( blob );
			var img = document.createElement( 'img' );
			img.className = 'jbptm-preview';
			img.src = url;
			img.alt = '';
			box.appendChild( img );

			var fn = field( __( 'Filename', 'jb-paste-to-media' ) + ' (.' + ext + ')', 'filename', values.filename );
			box.appendChild( fn );
			box.appendChild( field( __( 'Alt text', 'jb-paste-to-media' ), 'alt', values.alt ) );
			box.appendChild( field( __( 'Title', 'jb-paste-to-media' ), 'title', values.title ) );
			box.appendChild( field( __( 'Caption', 'jb-paste-to-media' ), 'caption', values.caption ) );

			var actions = document.createElement( 'div' );
			actions.className = 'jbptm-actions';
			var cancel = document.createElement( 'button' );
			cancel.type = 'button';
			cancel.className = 'button';
			cancel.textContent = __( 'Cancel', 'jb-paste-to-media' );
			var ok = document.createElement( 'button' );
			ok.type = 'submit';
			ok.className = 'button button-primary';
			ok.textContent = __( 'Upload', 'jb-paste-to-media' );
			actions.appendChild( cancel );
			actions.appendChild( ok );
			box.appendChild( actions );

			overlay.appendChild( box );
			document.body.appendChild( overlay );
			fn.querySelector( 'input' ).focus();
			fn.querySelector( 'input' ).select();

			function close( result ) {
				URL.revokeObjectURL( url );
				overlay.remove();
				if ( previous && previous.focus ) {
					try { previous.focus(); } catch ( e ) {}
				}
				resolve( result );
			}
			box.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var out = Object.assign( {}, values );
				[ 'filename', 'alt', 'title', 'caption' ].forEach( function ( k ) {
					out[ k ] = box.elements[ k ].value;
				} );
				close( out );
			} );
			cancel.addEventListener( 'click', function () { close( null ); } );
			overlay.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					e.stopPropagation();
					close( null );
				}
			} );
		} );
	}

	/**
	 * Run one file through conversion, naming, tagging and (optionally) the dialog.
	 * Resolves to { file, meta } or null if the user cancelled.
	 */
	function process( file ) {
		var s = data.image || {};
		var ctx = context();
		return convert( file ).then( function ( blob ) {
			var ext = EXT[ blob.type ] || ( file.name.split( '.' ).pop() || 'png' );
			var values = {
				filename: render( s.filename, ctx ),
				alt: render( s.alt, ctx ),
				title: render( s.title, ctx ),
				caption: render( s.caption, ctx ),
				description: render( s.description, ctx )
			};
			var ready = data.mode === 'prompt' ? dialog( blob, values, ext ) : Promise.resolve( values );
			return ready.then( function ( v ) {
				if ( ! v ) {
					return null;
				}
				var base = slugify( v.filename.replace( /\.[a-z0-9]{2,4}$/i, '' ) ) || 'pasted-image';
				var named = new File( [ blob ], base + '.' + ext, { type: blob.type || file.type } );
				return {
					file: named,
					meta: {
						alt_text: v.alt,
						title: v.title,
						caption: v.caption,
						description: v.description,
						jbptm_paste: 1
					}
				};
			} );
		} );
	}

	/** Upload directly through the REST API (used by the Classic editor). */
	function upload( file, meta, postId ) {
		var body = new window.FormData();
		body.append( 'file', file, file.name );
		Object.keys( meta ).forEach( function ( k ) {
			if ( meta[ k ] !== '' && meta[ k ] !== undefined ) {
				body.append( k, meta[ k ] );
			}
		} );
		if ( postId ) {
			body.append( 'post', postId );
		}
		return window.wp.apiFetch( { path: '/wp/v2/media', method: 'POST', body: body } );
	}

	window.jbptm = {
		settings: data,
		context: context,
		render: render,
		slugify: slugify,
		imageFiles: imageFiles,
		isImagePaste: isImagePaste,
		convert: convert,
		process: process,
		upload: upload,
		__: __
	};
} )( window, document );
