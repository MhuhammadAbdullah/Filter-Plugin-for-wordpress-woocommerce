/**
 * MAB Commerce Filters — admin UI: filter builder drag & drop, list
 * actions, locations, cache/log utilities, color & media pickers.
 */
( function () {
	'use strict';

	function ajax( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		var nonce = document.getElementById( 'mabcf-builder-nonce' ) || document.getElementById( 'mabcf-locations-nonce' );
		body.append( 'nonce', ( window.mabcfAdmin && window.mabcfAdmin.nonce ) || ( nonce ? nonce.value : '' ) );

		return fetch( window.mabcfAdmin ? window.mabcfAdmin.ajaxUrl : ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	/* ---------- Color picker ---------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.mabcf-color-field' ).wpColorPicker();
		}
	} );

	/* ---------- Media picker (term meta fields) ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mabcf-media-select' );
		if ( ! btn || ! window.wp || ! window.wp.media ) {
			return;
		}
		e.preventDefault();

		var targetInput = document.getElementById( btn.dataset.target );
		var frame = window.wp.media( { title: btn.textContent, multiple: false } );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			if ( targetInput ) {
				targetInput.value = attachment.url;
			}
		} );

		frame.open();
	} );

	/* ---------- Filter Sets list: duplicate / delete / toggle ---------- */

	document.addEventListener( 'click', function ( e ) {
		var dup = e.target.closest( '.mabcf-duplicate-set' );
		var del = e.target.closest( '.mabcf-delete-set' );
		var toggle = e.target.closest( '.mabcf-toggle-set' );

		if ( dup ) {
			ajax( 'mabcf_admin_duplicate_filter_set', { id: dup.dataset.id } ).then( function ( res ) {
				if ( res.success ) {
					window.location.href = res.data.edit_url;
				}
			} );
		}

		if ( del ) {
			if ( ! window.confirm( window.mabcfAdmin.i18n.confirmDelete ) ) {
				return;
			}
			ajax( 'mabcf_admin_delete_filter_set', { id: del.dataset.id } ).then( function ( res ) {
				if ( res.success ) {
					del.closest( 'tr' ).remove();
				}
			} );
		}

		if ( toggle ) {
			ajax( 'mabcf_admin_toggle_filter_set', { id: toggle.dataset.id, status: toggle.dataset.status } ).then( function ( res ) {
				if ( res.success ) {
					window.location.reload();
				}
			} );
		}
	} );

	/* ---------- Locations page ---------- */

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '#mabcf-add-location' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var set = document.getElementById( 'mabcf-location-set' );
		var type = document.getElementById( 'mabcf-location-type' );
		var value = document.getElementById( 'mabcf-location-value' );

		ajax( 'mabcf_admin_add_location', {
			filter_set_id: set.value,
			type: type.value,
			value: 'global' === type.value || 'shop' === type.value ? '' : value.value,
		} ).then( function ( res ) {
			if ( res.success ) {
				window.location.reload();
			}
		} );
	} );

	document.addEventListener( 'click', function ( e ) {
		var del = e.target.closest( '.mabcf-delete-location' );
		if ( ! del ) {
			return;
		}
		ajax( 'mabcf_admin_delete_location', { id: del.dataset.id } ).then( function ( res ) {
			if ( res.success ) {
				del.closest( 'tr' ).remove();
			}
		} );
	} );

	/* ---------- Performance page: clear cache ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mabcf-clear-cache' );
		if ( ! btn ) {
			return;
		}
		ajax( 'mabcf_admin_clear_cache', {} ).then( function ( res ) {
			var status = btn.parentElement.querySelector( '.mabcf-save-status' );
			if ( status ) {
				status.textContent = res.success ? window.mabcfAdmin.i18n.saved : window.mabcfAdmin.i18n.error;
			}
		} );
	} );

	/* ---------- Logs page: clear logs ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mabcf-clear-logs' );
		if ( ! btn ) {
			return;
		}
		if ( ! window.confirm( window.mabcfAdmin.i18n.confirmDelete ) ) {
			return;
		}
		ajax( 'mabcf_admin_clear_logs', {} ).then( function ( res ) {
			if ( res.success ) {
				window.location.reload();
			}
		} );
	} );

	/* ================= Filter Builder ================= */

	var Builder = {
		canvas: null,

		init: function () {
			this.canvas = document.getElementById( 'mabcf-canvas' );
			if ( ! this.canvas ) {
				return;
			}

			this.bindPalette();
			this.bindCanvasDrop();
			this.bindItemEvents();
			this.bindSave();
		},

		bindPalette: function () {
			document.querySelectorAll( '.mabcf-palette-item' ).forEach( function ( item ) {
				item.addEventListener( 'dragstart', function ( e ) {
					e.dataTransfer.setData( 'text/mabcf-new-type', item.dataset.type );
				} );
			} );
		},

		bindCanvasDrop: function () {
			var canvas = this.canvas;

			canvas.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				canvas.classList.add( 'is-dragover' );
			} );

			canvas.addEventListener( 'dragleave', function () {
				canvas.classList.remove( 'is-dragover' );
			} );

			canvas.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				canvas.classList.remove( 'is-dragover' );

				var newType = e.dataTransfer.getData( 'text/mabcf-new-type' );
				if ( newType ) {
					Builder.addItem( newType );
					return;
				}

				var draggingId = e.dataTransfer.getData( 'text/mabcf-reorder' );
				if ( ! draggingId ) {
					return;
				}

				var dragging = canvas.querySelector( '[data-drag-id="' + draggingId + '"]' );
				var after = Builder.itemAfter( e.clientY );

				if ( ! dragging ) {
					return;
				}

				if ( after ) {
					canvas.insertBefore( dragging, after );
				} else {
					canvas.appendChild( dragging );
				}
			} );
		},

		itemAfter: function ( y ) {
			var items = Array.prototype.slice.call( this.canvas.querySelectorAll( '.mabcf-builder-item:not(.is-dragging)' ) );
			var closest = null;
			var closestOffset = Number.NEGATIVE_INFINITY;

			items.forEach( function ( item ) {
				var box = item.getBoundingClientRect();
				var offset = y - box.top - box.height / 2;
				if ( offset < 0 && offset > closestOffset ) {
					closestOffset = offset;
					closest = item;
				}
			} );

			return closest;
		},

		addItem: function ( type ) {
			var labels = {
				category: 'Category',
				taxonomy: 'Taxonomy (Tags/Brand/Custom)',
				attribute: 'Attribute (Color/Size/Swatch)',
				price: 'Price Slider',
				rating: 'Rating',
				stock: 'Stock Status',
				sale: 'Sale / Featured / New',
				search: 'Search Box',
				meta: 'Custom Field',
			};

			var styleOptions = {
				category: [ [ 'list', 'List' ] ],
				taxonomy: [ [ 'list', 'List' ], [ 'pill', 'Pills' ], [ 'brand-image', 'Brand (Image)' ], [ 'brand-logo', 'Brand (Logo)' ] ],
				attribute: [ [ 'color', 'Color Swatch' ], [ 'image', 'Image Swatch' ], [ 'pill', 'Size Pill' ], [ 'list', 'List' ] ],
				price: [ [ 'slider', 'Dual Slider' ] ],
				rating: [ [ 'list', 'List' ] ],
				stock: [ [ 'list', 'List' ] ],
				sale: [ [ 'list', 'List' ] ],
				search: [ [ 'box', 'Search Box' ] ],
				meta: [ [ 'text', 'Text' ], [ 'number', 'Number Range' ], [ 'boolean', 'Toggle' ], [ 'date', 'Date Range' ] ],
			};

			var options = ( styleOptions[ type ] || [ [ 'list', 'List' ] ] )
				.map( function ( pair ) {
					return '<option value="' + pair[ 0 ] + '">' + pair[ 1 ] + '</option>';
				} )
				.join( '' );

			var dragId = 'new-' + Date.now() + '-' + Math.floor( Math.random() * 1000 );

			var el = document.createElement( 'div' );
			el.className = 'mabcf-builder-item is-open';
			el.setAttribute( 'draggable', 'true' );
			el.dataset.id = '0';
			el.dataset.type = type;
			el.dataset.dragId = dragId;
			el.innerHTML =
				'<div class="mabcf-builder-item__head">' +
				'<span class="mabcf-builder-item__drag" aria-hidden="true">::</span>' +
				'<strong class="mabcf-builder-item__type">' + ( labels[ type ] || type ) + '</strong>' +
				'<input type="text" class="mabcf-field-label" value="" placeholder="Filter label">' +
				'<button type="button" class="button-link mabcf-item-toggle">Edit</button>' +
				'<button type="button" class="button-link-delete mabcf-item-remove">Remove</button>' +
				'</div>' +
				'<div class="mabcf-builder-item__body">' +
				'<div class="mabcf-field-row">' +
				'<label>Source (taxonomy/attribute/meta key)<input type="text" class="mabcf-field-source" value="" placeholder="e.g. pa_color, product_tag, _brand"></label>' +
				'<label>Display Style<select class="mabcf-field-display">' + options + '</select></label>' +
				'</div>' +
				'<div class="mabcf-field-row">' +
				'<label><input type="checkbox" class="mabcf-field-show-count"> Show product count</label>' +
				'<label><input type="checkbox" class="mabcf-field-collapsible" checked> Collapsible</label>' +
				'<label><input type="checkbox" class="mabcf-field-searchable"> Searchable list</label>' +
				'</div>' +
				'</div>';

			this.canvas.appendChild( el );
		},

		bindItemEvents: function () {
			var canvas = this.canvas;

			canvas.addEventListener( 'dragstart', function ( e ) {
				var item = e.target.closest( '.mabcf-builder-item' );
				if ( ! item ) {
					return;
				}
				if ( ! item.dataset.dragId ) {
					item.dataset.dragId = 'item-' + item.dataset.id + '-' + Date.now();
				}
				e.dataTransfer.setData( 'text/mabcf-reorder', item.dataset.dragId );
				item.classList.add( 'is-dragging' );
			} );

			canvas.addEventListener( 'dragend', function ( e ) {
				var item = e.target.closest( '.mabcf-builder-item' );
				if ( item ) {
					item.classList.remove( 'is-dragging' );
				}
			} );

			canvas.addEventListener( 'click', function ( e ) {
				var toggle = e.target.closest( '.mabcf-item-toggle' );
				var remove = e.target.closest( '.mabcf-item-remove' );

				if ( toggle ) {
					toggle.closest( '.mabcf-builder-item' ).classList.toggle( 'is-open' );
				}

				if ( remove && window.confirm( window.mabcfAdmin.i18n.confirmDelete ) ) {
					remove.closest( '.mabcf-builder-item' ).remove();
				}
			} );
		},

		serialize: function () {
			return Array.prototype.map.call( this.canvas.querySelectorAll( '.mabcf-builder-item' ), function ( item ) {
				return {
					id: item.dataset.id || 0,
					type: item.dataset.type,
					label: item.querySelector( '.mabcf-field-label' ).value,
					source_key: item.querySelector( '.mabcf-field-source' ).value,
					display_style: item.querySelector( '.mabcf-field-display' ).value,
					settings: {
						show_count: item.querySelector( '.mabcf-field-show-count' ).checked,
						collapsible: item.querySelector( '.mabcf-field-collapsible' ).checked,
						searchable: item.querySelector( '.mabcf-field-searchable' ).checked,
					},
				};
			} );
		},

		bindSave: function () {
			var saveBtn = document.querySelector( '.mabcf-save-set' );
			if ( ! saveBtn ) {
				return;
			}

			saveBtn.addEventListener( 'click', function () {
				var wrap = document.querySelector( '.mabcf-builder' );
				var status = document.querySelector( '.mabcf-save-status' );

				var payload = {
					id: wrap.dataset.setId,
					name: document.getElementById( 'mabcf-field-name' ).value,
					layout: document.getElementById( 'mabcf-field-layout' ).value,
					status: document.getElementById( 'mabcf-field-status' ).value,
					'settings[ajax]': document.getElementById( 'mabcf-field-settings_ajax' ).checked ? 1 : 0,
					'settings[instant]': document.getElementById( 'mabcf-field-settings_instant' ).checked ? 1 : 0,
					'settings[change_url]': document.getElementById( 'mabcf-field-settings_change_url' ).checked ? 1 : 0,
					'settings[apply_button]': document.getElementById( 'mabcf-field-settings_apply_button' ).checked ? 1 : 0,
					'settings[collapsible]': document.getElementById( 'mabcf-field-settings_collapsible' ).checked ? 1 : 0,
					'settings[show_sorting]': document.getElementById( 'mabcf-field-settings_show_sorting' ).checked ? 1 : 0,
					'settings[infinite_scroll]': document.getElementById( 'mabcf-field-settings_infinite_scroll' ).checked ? 1 : 0,
					'settings[columns]': document.getElementById( 'mabcf-field-settings_columns' ).value,
				};

				var body = new FormData();
				body.append( 'action', 'mabcf_admin_save_filter_set' );
				body.append( 'nonce', document.getElementById( 'mabcf-builder-nonce' ).value );

				Object.keys( payload ).forEach( function ( key ) {
					body.append( key, payload[ key ] );
				} );

				Builder.serialize().forEach( function ( filter, index ) {
					body.append( 'filters[' + index + '][id]', filter.id );
					body.append( 'filters[' + index + '][type]', filter.type );
					body.append( 'filters[' + index + '][label]', filter.label );
					body.append( 'filters[' + index + '][source_key]', filter.source_key );
					body.append( 'filters[' + index + '][display_style]', filter.display_style );
					body.append( 'filters[' + index + '][settings][show_count]', filter.settings.show_count ? 1 : 0 );
					body.append( 'filters[' + index + '][settings][collapsible]', filter.settings.collapsible ? 1 : 0 );
					body.append( 'filters[' + index + '][settings][searchable]', filter.settings.searchable ? 1 : 0 );
				} );

				status.textContent = '…';
				status.classList.remove( 'is-error' );

				fetch( window.mabcfAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) {
						return r.json();
					} )
					.then( function ( res ) {
						if ( res.success ) {
							status.textContent = window.mabcfAdmin.i18n.saved;
							wrap.dataset.setId = res.data.id;
							if ( '0' === new URL( window.location.href ).searchParams.get( 'set' ) ) {
								window.location.href = res.data.edit_url;
							}
						} else {
							status.textContent = ( res.data && res.data.message ) || window.mabcfAdmin.i18n.error;
							status.classList.add( 'is-error' );
						}
					} )
					['catch']( function () {
						status.textContent = window.mabcfAdmin.i18n.error;
						status.classList.add( 'is-error' );
					} );
			} );
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		Builder.init();
	} );
} )();
