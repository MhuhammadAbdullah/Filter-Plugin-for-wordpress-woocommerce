/**
 * Advanced Product Filters — wp-admin builder runtime.
 *
 * Drives the Filter Sets list (drag reorder, enable toggle, duplicate,
 * delete) and the Filter Set editor (locations, sections drag-and-drop,
 * behaviour overrides, live preview), plus the Import/Export and Reset
 * actions. Vanilla JS, no build step.
 */
( function () {
	'use strict';

	if ( typeof window.APF_ADMIN === 'undefined' ) {
		return;
	}

	var ADMIN = window.APF_ADMIN;

	function qs( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function qsa( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( value === null || value === undefined ) {
				return;
			}
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key.indexOf( 'on' ) === 0 && typeof value === 'function' ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else {
				node.setAttribute( key, value );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function ajax( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', ADMIN.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( ADMIN.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} );
	}

	function restGet( path ) {
		return fetch( ADMIN.restUrl + path, { credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Generic drag-and-drop reordering for a list of sibling elements      */
	/* ------------------------------------------------------------------ */

	function makeSortable( container, itemSelector, onReorder ) {
		var dragged = null;

		container.addEventListener( 'dragstart', function ( event ) {
			var item = event.target.closest( itemSelector );
			if ( ! item ) {
				return;
			}
			dragged = item;
			item.classList.add( 'apf-dragging' );
			event.dataTransfer.effectAllowed = 'move';
		} );

		container.addEventListener( 'dragend', function () {
			if ( dragged ) {
				dragged.classList.remove( 'apf-dragging' );
			}
			dragged = null;
			onReorder();
		} );

		container.addEventListener( 'dragover', function ( event ) {
			event.preventDefault();
			var target = event.target.closest( itemSelector );
			if ( ! target || target === dragged || ! dragged ) {
				return;
			}
			var rect = target.getBoundingClientRect();
			var before = ( event.clientY - rect.top ) / rect.height < 0.5;
			container.insertBefore( dragged, before ? target : target.nextSibling );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Filter Sets list page                                               */
	/* ------------------------------------------------------------------ */

	function initListPage() {
		var table = qs( '#apf-filter-sets-table' );
		if ( ! table ) {
			return;
		}
		var body = qs( '#apf-filter-sets-body' );

		qsa( 'tr', body ).forEach( function ( row ) {
			row.setAttribute( 'draggable', 'true' );
		} );

		makeSortable( body, 'tr', function () {
			var order = qsa( 'tr', body ).map( function ( row ) {
				return row.dataset.id;
			} );
			ajax( 'apf_reorder_filter_sets', { order: JSON.stringify( order ) } );
		} );

		body.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '.apf-toggle-enabled' ) ) {
				return;
			}
			ajax( 'apf_toggle_filter_set', {
				id: event.target.dataset.id,
				enabled: event.target.checked ? '1' : '',
			} );
		} );

		body.addEventListener( 'click', function ( event ) {
			var duplicate = event.target.closest( '.apf-duplicate' );
			if ( duplicate ) {
				event.preventDefault();
				ajax( 'apf_duplicate_filter_set', { id: duplicate.dataset.id } ).then( function () {
					window.location.reload();
				} );
				return;
			}

			var del = event.target.closest( '.apf-delete' );
			if ( del ) {
				event.preventDefault();
				if ( ! window.confirm( ADMIN.i18n.confirmDelete ) ) {
					return;
				}
				ajax( 'apf_delete_filter_set', { id: del.dataset.id } ).then( function () {
					window.location.reload();
				} );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Filter Set editor page                                              */
	/* ------------------------------------------------------------------ */

	function initEditorPage() {
		var dataEl = qs( '#apf-editor-data' );
		if ( ! dataEl ) {
			return;
		}

		var data = JSON.parse( dataEl.textContent );
		var state = {
			id: data.id || 0,
			locations: parseLocations( data.locations || [] ),
			sections: ( data.sections && data.sections.length ) ? data.sections.slice() : [],
			settings: data.settings || {},
		};

		renderLocations( state );
		renderSections( state );
		renderBehavior( state );

		qs( '#apf-save-fs' ).addEventListener( 'click', function () {
			save( state );
		} );
	}

	function parseLocations( locations ) {
		var state = { shop: false, all_archives: false, product_cat: [], product_tag: [], brand: [], taxonomies: {}, pages: [], elementor_templates: [] };

		locations.forEach( function ( location ) {
			if ( location.type === 'shop' ) {
				state.shop = true;
			} else if ( location.type === 'all_archives' ) {
				state.all_archives = true;
			} else if ( location.type === 'product_cat' ) {
				state.product_cat = location.ids || [];
			} else if ( location.type === 'product_tag' ) {
				state.product_tag = location.ids || [];
			} else if ( location.type === 'brand' ) {
				state.brand = location.ids || [];
			} else if ( location.type === 'page' ) {
				state.pages = location.ids || [];
			} else if ( location.type === 'elementor_template' ) {
				state.elementor_templates = location.ids || [];
			} else if ( location.type === 'taxonomy' && location.taxonomy ) {
				state.taxonomies[ location.taxonomy ] = location.ids || [];
			}
		} );

		return state;
	}

	function serializeLocations( state ) {
		var locations = [];

		if ( state.shop ) {
			locations.push( { type: 'shop' } );
		}
		if ( state.all_archives ) {
			locations.push( { type: 'all_archives' } );
		}
		if ( state.product_cat.length ) {
			locations.push( { type: 'product_cat', ids: state.product_cat } );
		}
		if ( state.product_tag.length ) {
			locations.push( { type: 'product_tag', ids: state.product_tag } );
		}
		if ( state.brand.length ) {
			locations.push( { type: 'brand', ids: state.brand } );
		}
		if ( state.pages.length ) {
			locations.push( { type: 'page', ids: state.pages } );
		}
		if ( state.elementor_templates.length ) {
			locations.push( { type: 'elementor_template', ids: state.elementor_templates } );
		}
		Object.keys( state.taxonomies ).forEach( function ( taxonomy ) {
			if ( state.taxonomies[ taxonomy ].length ) {
				locations.push( { type: 'taxonomy', taxonomy: taxonomy, ids: state.taxonomies[ taxonomy ] } );
			}
		} );

		return locations;
	}

	function renderLocations( state ) {
		var mount = qs( '#apf-locations-app' );
		mount.innerHTML = '';

		mount.appendChild(
			el( 'label', {}, [
				el( 'input', {
					type: 'checkbox',
					checked: state.shop ? 'checked' : null,
					onchange: function ( event ) {
						state.shop = event.target.checked;
					},
				} ),
				document.createTextNode( ' ' + ( ADMIN.i18n.shopPage || 'Shop Page' ) ),
			] )
		);

		mount.appendChild( document.createElement( 'br' ) );

		mount.appendChild(
			el( 'label', {}, [
				el( 'input', {
					type: 'checkbox',
					checked: state.all_archives ? 'checked' : null,
					onchange: function ( event ) {
						state.all_archives = event.target.checked;
					},
				} ),
				document.createTextNode( ' All Product Archives' ),
			] )
		);

		mount.appendChild( buildTermGroup( 'Categories', 'product_cat', state, 'product_cat' ) );
		mount.appendChild( buildTermGroup( 'Tags', 'product_tag', state, 'product_tag' ) );

		if ( ADMIN.brandTaxonomy ) {
			mount.appendChild( buildTermGroup( 'Brands', ADMIN.brandTaxonomy, state, 'brand' ) );
		}

		Object.keys( ADMIN.customTaxonomies || {} ).forEach( function ( taxonomy ) {
			mount.appendChild( buildTaxonomyGroup( ADMIN.customTaxonomies[ taxonomy ], taxonomy, state ) );
		} );

		mount.appendChild( buildPageGroup( state ) );
		mount.appendChild( buildElementorTemplateGroup( state ) );
	}

	function buildTermGroup( label, taxonomy, state, stateKey ) {
		var details = el( 'details', { class: 'apf-location-group' } );
		var summary = el( 'summary', { text: label } );
		var list = el( 'div', { class: 'apf-location-term-list' } );
		details.appendChild( summary );
		details.appendChild( list );

		details.addEventListener(
			'toggle',
			function loadOnce() {
				if ( ! details.open || list.dataset.loaded ) {
					return;
				}
				list.dataset.loaded = '1';
				restGet( '/terms?taxonomy=' + encodeURIComponent( taxonomy ) + '&search=' ).then( function ( terms ) {
					terms.forEach( function ( term ) {
						list.appendChild( buildCheckbox( term.name + ' (' + term.count + ')', term.id, state[ stateKey ], function ( checked ) {
							toggleId( state[ stateKey ], term.id, checked );
						} ) );
					} );
				} );
			},
			{ once: false }
		);

		return details;
	}

	function buildTaxonomyGroup( label, taxonomy, state ) {
		if ( ! state.taxonomies[ taxonomy ] ) {
			state.taxonomies[ taxonomy ] = [];
		}

		var details = el( 'details', { class: 'apf-location-group' } );
		var summary = el( 'summary', { text: label } );
		var list = el( 'div', { class: 'apf-location-term-list' } );
		details.appendChild( summary );
		details.appendChild( list );

		details.addEventListener( 'toggle', function () {
			if ( ! details.open || list.dataset.loaded ) {
				return;
			}
			list.dataset.loaded = '1';
			restGet( '/terms?taxonomy=' + encodeURIComponent( taxonomy ) + '&search=' ).then( function ( terms ) {
				terms.forEach( function ( term ) {
					list.appendChild( buildCheckbox( term.name + ' (' + term.count + ')', term.id, state.taxonomies[ taxonomy ], function ( checked ) {
						toggleId( state.taxonomies[ taxonomy ], term.id, checked );
					} ) );
				} );
			} );
		} );

		return details;
	}

	function buildPageGroup( state ) {
		var details = el( 'details', { class: 'apf-location-group' } );
		var summary = el( 'summary', { text: 'Specific Pages' } );
		var list = el( 'div', { class: 'apf-location-term-list' } );
		details.appendChild( summary );
		details.appendChild( list );

		details.addEventListener( 'toggle', function () {
			if ( ! details.open || list.dataset.loaded ) {
				return;
			}
			list.dataset.loaded = '1';
			restGet( '/pages?search=' ).then( function ( pages ) {
				pages.forEach( function ( page ) {
					list.appendChild( buildCheckbox( page.name, page.id, state.pages, function ( checked ) {
						toggleId( state.pages, page.id, checked );
					} ) );
				} );
			} );
		} );

		return details;
	}

	function buildElementorTemplateGroup( state ) {
		var details = el( 'details', { class: 'apf-location-group' } );
		var summary = el( 'summary', { text: 'Elementor Templates' } );
		var list = el( 'div', { class: 'apf-location-term-list' } );
		details.appendChild( summary );
		details.appendChild( list );

		details.addEventListener( 'toggle', function () {
			if ( ! details.open || list.dataset.loaded ) {
				return;
			}
			list.dataset.loaded = '1';
			restGet( '/elementor-templates' ).then( function ( templates ) {
				if ( ! templates.length ) {
					list.appendChild( el( 'p', { text: 'No Elementor templates found.' } ) );
					return;
				}
				templates.forEach( function ( template ) {
					list.appendChild( buildCheckbox( template.name, template.id, state.elementor_templates, function ( checked ) {
						toggleId( state.elementor_templates, template.id, checked );
					} ) );
				} );
			} );
		} );

		return details;
	}

	function buildCheckbox( label, id, selectedArray, onChange ) {
		return el( 'label', { style: 'display:flex;align-items:center;gap:6px;' }, [
			el( 'input', {
				type: 'checkbox',
				checked: selectedArray.indexOf( id ) !== -1 ? 'checked' : null,
				onchange: function ( event ) {
					onChange( event.target.checked );
				},
			} ),
			document.createTextNode( label ),
		] );
	}

	function toggleId( array, id, checked ) {
		var index = array.indexOf( id );
		if ( checked && index === -1 ) {
			array.push( id );
		} else if ( ! checked && index !== -1 ) {
			array.splice( index, 1 );
		}
	}

	/* -- Sections ---------------------------------------------------- */

	function renderSections( state ) {
		var mount = qs( '#apf-sections-app' );
		mount.innerHTML = '';

		state.sections.forEach( function ( section, index ) {
			mount.appendChild( buildSectionCard( section, state ) );
		} );

		makeSortable( mount, '.apf-section-card', function () {
			var order = qsa( '.apf-section-card', mount ).map( function ( card ) {
				return card.dataset.sectionId;
			} );
			state.sections.sort( function ( a, b ) {
				return order.indexOf( a.id ) - order.indexOf( b.id );
			} );
		} );

		var addSelect = qs( '#apf-add-section-type' );
		addSelect.innerHTML = '';
		Object.keys( ADMIN.sectionTypes ).forEach( function ( type ) {
			if ( 'active_filters' === type ) {
				return;
			}
			addSelect.appendChild( el( 'option', { value: type, text: ADMIN.sectionTypes[ type ].label } ) );
		} );

		qs( '#apf-add-section-btn' ).onclick = function () {
			var type = addSelect.value;
			var meta = ADMIN.sectionTypes[ type ];
			var newSection = {
				id: type + '-' + Date.now(),
				type: type,
				label: meta.label,
				enabled: true,
				collapsed: false,
				show_count: true,
				input_type: ( meta.input_types || [] )[0] || '',
			};

			if ( 'attribute' === type ) {
				var firstAttribute = Object.keys( ADMIN.attributeTaxonomies || {} )[0];
				newSection.taxonomy = firstAttribute || '';
			}

			if ( 'custom_taxonomy' === type ) {
				var firstCustom = Object.keys( ADMIN.customTaxonomies || {} )[0];
				newSection.taxonomy = firstCustom || '';
			}

			state.sections.push( newSection );
			renderSections( state );
		};
	}

	function buildSectionCard( section, state ) {
		var meta = ADMIN.sectionTypes[ section.type ] || {};
		var card = el( 'div', { class: 'apf-section-card', draggable: 'true', 'data-section-id': section.id } );

		var header = el( 'div', { class: 'apf-section-card-header' }, [
			el( 'span', { class: 'apf-drag-handle dashicons dashicons-menu' } ),
			el( 'span', { class: 'apf-section-card-title', text: section.label || meta.label } ),
		] );

		if ( meta.collapsible !== false ) {
			var enabledSwitch = el( 'label', { class: 'apf-switch' }, [
				el( 'input', {
					type: 'checkbox',
					checked: section.enabled ? 'checked' : null,
					onchange: function ( event ) {
						section.enabled = event.target.checked;
					},
				} ),
				el( 'span', { class: 'apf-switch-track' } ),
			] );
			header.appendChild( enabledSwitch );
		}

		var expandButton = el( 'button', {
			type: 'button',
			class: 'button button-small',
			text: '⚙',
			onclick: function () {
				card.classList.toggle( 'apf-open' );
			},
		} );
		header.appendChild( expandButton );

		var removeButton = el( 'button', {
			type: 'button',
			class: 'button button-small',
			text: '✕',
			onclick: function () {
				var idx = state.sections.indexOf( section );
				if ( idx !== -1 ) {
					state.sections.splice( idx, 1 );
				}
				card.remove();
			},
		} );
		header.appendChild( removeButton );

		card.appendChild( header );

		var body = el( 'div', { class: 'apf-section-card-body' } );

		body.appendChild(
			el( 'label', {}, [
				document.createTextNode( 'Label' ),
				el( 'input', {
					type: 'text',
					value: section.label || '',
					oninput: function ( event ) {
						section.label = event.target.value;
						qs( '.apf-section-card-title', card ).textContent = event.target.value;
					},
				} ),
			] )
		);

		if ( meta.input_types && meta.input_types.length > 1 ) {
			var select = el( 'select', {
				onchange: function ( event ) {
					section.input_type = event.target.value;
				},
			} );
			meta.input_types.forEach( function ( inputType ) {
				var inputMeta = ADMIN.inputTypes[ inputType ] || { label: inputType };
				select.appendChild( el( 'option', { value: inputType, text: inputMeta.label, selected: section.input_type === inputType ? 'selected' : null } ) );
			} );
			body.appendChild( el( 'label', {}, [ document.createTextNode( 'Display As' ), select ] ) );
		}

		if ( 'attribute' === section.type ) {
			body.appendChild( buildTaxonomySelect( section, ADMIN.attributeTaxonomies || {} ) );
		}

		if ( 'custom_taxonomy' === section.type ) {
			body.appendChild( buildTaxonomySelect( section, ADMIN.customTaxonomies || {} ) );
		}

		if ( meta.taxonomy || 'attribute' === section.type || 'custom_taxonomy' === section.type || 'brand' === section.type || 'tag' === section.type ) {
			body.appendChild(
				el( 'label', { style: 'flex-direction:row;align-items:center;' }, [
					el( 'input', {
						type: 'checkbox',
						checked: section.show_count ? 'checked' : null,
						onchange: function ( event ) {
							section.show_count = event.target.checked;
						},
					} ),
					document.createTextNode( ' Show Product Count' ),
				] )
			);

			body.appendChild(
				el( 'label', { style: 'flex-direction:row;align-items:center;' }, [
					el( 'input', {
						type: 'checkbox',
						checked: section.searchable ? 'checked' : null,
						onchange: function ( event ) {
							section.searchable = event.target.checked;
						},
					} ),
					document.createTextNode( ' Add Search Box' ),
				] )
			);
		}

		body.appendChild(
			el( 'label', { style: 'flex-direction:row;align-items:center;' }, [
				el( 'input', {
					type: 'checkbox',
					checked: section.collapsed ? 'checked' : null,
					onchange: function ( event ) {
						section.collapsed = event.target.checked;
					},
				} ),
				document.createTextNode( ' Collapsed By Default' ),
			] )
		);

		card.appendChild( body );

		return card;
	}

	function buildTaxonomySelect( section, taxonomies ) {
		var select = el( 'select', {
			onchange: function ( event ) {
				section.taxonomy = event.target.value;
			},
		} );

		Object.keys( taxonomies ).forEach( function ( taxonomy ) {
			select.appendChild(
				el( 'option', {
					value: taxonomy,
					text: taxonomies[ taxonomy ],
					selected: section.taxonomy === taxonomy ? 'selected' : null,
				} )
			);
		} );

		return el( 'label', {}, [ document.createTextNode( 'Taxonomy' ), select ] );
	}

	/* -- Behaviour ----------------------------------------------------- */

	function renderBehavior( state ) {
		var mount = qs( '#apf-behavior-app' );
		mount.innerHTML = '';

		var fields = [
			[ 'instant_ajax', 'Instant AJAX' ],
			[ 'show_apply_button', 'Show Apply Button' ],
			[ 'show_clear_button', 'Show Clear Button' ],
			[ 'url_sync', 'Sync To URL' ],
			[ 'sticky_sidebar', 'Sticky Sidebar' ],
		];

		fields.forEach( function ( field ) {
			var key = field[0];
			var label = field[1];
			var current = state.settings[ key ];

			var select = el( 'select', {
				onchange: function ( event ) {
					if ( event.target.value === '' ) {
						delete state.settings[ key ];
					} else {
						state.settings[ key ] = event.target.value === '1';
					}
				},
			}, [
				el( 'option', { value: '', text: 'Use global default', selected: current === undefined ? 'selected' : null } ),
				el( 'option', { value: '1', text: 'On', selected: current === true ? 'selected' : null } ),
				el( 'option', { value: '0', text: 'Off', selected: current === false ? 'selected' : null } ),
			] );

			mount.appendChild( el( 'label', {}, [ document.createTextNode( label ), select ] ) );
		} );
	}

	/* -- Save ------------------------------------------------------------ */

	function save( state ) {
		var payload = {
			id: state.id,
			name: qs( '#apf-fs-name' ).value,
			enabled: qs( '#apf-fs-enabled' ).checked,
			priority: parseInt( qs( '#apf-fs-priority' ).value, 10 ) || 0,
			locations: serializeLocations( state.locations ),
			sections: state.sections,
			settings: state.settings,
		};

		var notice = qs( '#apf-save-notice' );

		ajax( 'apf_save_filter_set', { filter_set: JSON.stringify( payload ) } ).then( function ( response ) {
			notice.hidden = false;

			if ( response.success ) {
				notice.textContent = ADMIN.i18n.saved;
				notice.classList.remove( 'apf-notice-error' );

				if ( ! state.id && response.data.filterSet ) {
					state.id = response.data.filterSet.id;
					var url = new URL( window.location.href );
					url.searchParams.set( 'id', state.id );
					window.history.replaceState( {}, '', url.toString() );
				}

				var frame = qs( '#apf-preview-frame' );
				if ( frame && state.id ) {
					frame.src = frame.src.split( '#' )[0] + ( frame.src.indexOf( '?' ) === -1 ? '?' : '&' ) + 'apf_reload=' + Date.now();
				}
			} else {
				notice.textContent = ( response.data && response.data.message ) || ADMIN.i18n.saveError;
				notice.classList.add( 'apf-notice-error' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Settings page reset                                                  */
	/* ------------------------------------------------------------------ */

	function initSettingsPage() {
		var resetButton = qs( '.apf-reset-plugin' );
		if ( ! resetButton ) {
			return;
		}

		resetButton.addEventListener( 'click', function () {
			if ( ! window.confirm( ADMIN.i18n.confirmReset ) ) {
				return;
			}
			ajax( 'apf_reset_plugin' ).then( function () {
				window.location.reload();
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Import / Export page                                                 */
	/* ------------------------------------------------------------------ */

	function initImportExportPage() {
		var exportButton = qs( '#apf-export-btn' );
		if ( ! exportButton ) {
			return;
		}

		exportButton.addEventListener( 'click', function () {
			ajax( 'apf_export_settings' ).then( function ( response ) {
				if ( ! response.success ) {
					return;
				}
				var blob = new Blob( [ JSON.stringify( response.data, null, 2 ) ], { type: 'application/json' } );
				var url = URL.createObjectURL( blob );
				var link = el( 'a', { href: url, download: 'advanced-product-filters-export.json' } );
				document.body.appendChild( link );
				link.click();
				link.remove();
				URL.revokeObjectURL( url );
			} );
		} );

		qs( '#apf-import-btn' ).addEventListener( 'click', function () {
			var fileInput = qs( '#apf-import-file' );
			var result = qs( '#apf-import-result' );

			if ( ! fileInput.files.length ) {
				return;
			}

			var reader = new FileReader();
			reader.onload = function () {
				ajax( 'apf_import_settings', { payload: reader.result } ).then( function ( response ) {
					result.hidden = false;
					if ( response.success ) {
						result.textContent = 'Imported ' + response.data.imported_filter_sets + ' Filter Set(s).';
						result.classList.remove( 'apf-notice-error' );
					} else {
						result.textContent = ( response.data && response.data.message ) || 'Import failed.';
						result.classList.add( 'apf-notice-error' );
					}
				} );
			};
			reader.readAsText( fileInput.files[0] );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initListPage();
		initEditorPage();
		initSettingsPage();
		initImportExportPage();
	} );
} )();
