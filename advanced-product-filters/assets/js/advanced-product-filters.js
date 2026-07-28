/**
 * Advanced Product Filters — storefront runtime.
 *
 * Dependency-free vanilla JS (no jQuery). Handles collapsible sections,
 * the dual-handle price slider, AJAX filtering across the auto-injected
 * sidebar AND any standalone Elementor filter/grid widgets sharing the
 * same "scope", URL sync with browser back/forward support, the mobile
 * offcanvas panel, WooCommerce sorting/pagination interception, and an
 * optional infinite-scroll mode.
 */
( function () {
	'use strict';

	if ( typeof window.APF_CONFIG === 'undefined' ) {
		return;
	}

	var CONFIG = window.APF_CONFIG;

	/* ------------------------------------------------------------------ */
	/* Small utilities                                                     */
	/* ------------------------------------------------------------------ */

	function qs( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function qsa( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function debounce( fn, wait ) {
		var timer = null;
		return function () {
			var args = arguments;
			var context = this;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( context, args );
			}, wait );
		};
	}

	function buildQuery( params ) {
		var pairs = [];
		Object.keys( params ).forEach( function ( key ) {
			var value = params[ key ];
			if ( value === null || value === undefined || value === '' ) {
				return;
			}
			if ( Array.isArray( value ) ) {
				if ( value.length ) {
					pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( value.join( ',' ) ) );
				}
				return;
			}
			pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( value ) );
		} );
		return pairs.join( '&' );
	}

	/* ------------------------------------------------------------------ */
	/* Scope: a group of filter controls + grid(s) that update together    */
	/* ------------------------------------------------------------------ */

	/**
	 * A "scope" is every element sharing the same `data-apf-scope` value:
	 * the auto-injected sidebar (scope "context"), any standalone
	 * Elementor filter widgets, and any standalone Product Grid widgets.
	 * They are collected once and always updated together so multiple
	 * Elementor widgets on the same page stay in sync automatically.
	 */
	function Scope( key ) {
		this.key = key;
		this.filterSetId = 0;
		this.sidebar = null;
		this.standaloneWidgets = [];
		this.grids = [];
		this.ajaxSearchState = {};
	}

	Scope.prototype.addSidebar = function ( el ) {
		this.sidebar = el;
		this.filterSetId = parseInt( el.getAttribute( 'data-filter-set-id' ), 10 ) || 0;
	};

	Scope.prototype.addStandaloneWidget = function ( el ) {
		this.standaloneWidgets.push( el );
		if ( ! this.filterSetId ) {
			this.filterSetId = parseInt( el.getAttribute( 'data-filter-set-id' ), 10 ) || 0;
		}
	};

	Scope.prototype.addGrid = function ( el ) {
		this.grids.push( el );
		if ( ! this.filterSetId ) {
			this.filterSetId = parseInt( el.getAttribute( 'data-filter-set-id' ), 10 ) || 0;
		}
	};

	Scope.prototype.containers = function () {
		var all = this.standaloneWidgets.slice();
		if ( this.sidebar ) {
			all.push( this.sidebar );
		}
		return all;
	};

	/**
	 * Serialises every filter control found across this scope's
	 * containers into a flat params object suitable for the REST/AJAX
	 * filter endpoint and for the URL query string.
	 */
	Scope.prototype.serialize = function () {
		var params = {};

		this.containers().forEach( function ( container ) {
			qsa( 'input[name], select[name]', container ).forEach( function ( field ) {
				var name = field.name.replace( /\[\]$/, '' );

				if ( field.type === 'checkbox' ) {
					if ( ! field.checked ) {
						return;
					}
					params[ name ] = params[ name ] || [];
					params[ name ].push( field.value );
					return;
				}

				if ( field.type === 'radio' ) {
					if ( field.checked ) {
						params[ name ] = field.value;
					}
					return;
				}

				if ( field.tagName === 'SELECT' ) {
					if ( field.value !== '' ) {
						params[ name ] = field.value;
					}
					return;
				}

				if ( field.type === 'range' ) {
					return; // Handled explicitly below via the slider's own state.
				}
			} );

			qsa( '.apf-price-slider', container ).forEach( function ( slider ) {
				var min = slider.querySelector( '.apf-price-input-min' );
				var max = slider.querySelector( '.apf-price-input-max' );
				if ( min ) {
					params.apf_price_min = min.value;
				}
				if ( max ) {
					params.apf_price_max = max.value;
				}
			} );
		} );

		Object.keys( this.ajaxSearchState ).forEach( function ( param ) {
			var values = this.ajaxSearchState[ param ];
			if ( values && values.length ) {
				params[ param ] = ( params[ param ] || [] ).concat( values );
			}
		}, this );

		return params;
	};

	Scope.prototype.setLoading = function ( loading ) {
		this.containers().concat( this.grids ).forEach( function ( el ) {
			el.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		} );
	};

	Scope.prototype.refresh = function ( extraParams, updateHistory ) {
		var params = this.serialize();
		Object.assign( params, extraParams || {} );

		if ( this.filterSetId ) {
			params.filter_set_id = this.filterSetId;
		}

		this.setLoading( true );

		var query = buildQuery( params );
		var url = CONFIG.restUrl + '/filter' + ( query ? '?' + query : '' );

		return fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': CONFIG.nonce },
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				this.applyResponse( data );

				if ( updateHistory !== false ) {
					syncUrl( params );
				}

				return data;
			}.bind( this ) )
			.catch( function () {
				this.setLoading( false );
			}.bind( this ) );
	};

	Scope.prototype.applyResponse = function ( data ) {
		if ( this.sidebar && typeof data.sidebar_html === 'string' ) {
			this.sidebar.outerHTML = data.sidebar_html;
			this.sidebar = qs( '.apf-sidebar[data-filter-set-id="' + this.filterSetId + '"]' ) || qs( '.apf-sidebar' );
			if ( this.sidebar ) {
				bindSidebar( this.sidebar, this );
			}
		}

		this.grids.forEach( function ( grid ) {
			if ( typeof data.grid_html === 'string' ) {
				delete grid.dataset.apfCurrentPage;
				grid.innerHTML = data.grid_html;
				bindPagination( grid, this );
			}
		}, this );

		var mainGridTarget = qs( '#apf-ajax-grid-target[data-filter-set-id="' + this.filterSetId + '"]' );
		if ( mainGridTarget && typeof data.grid_html === 'string' ) {
			delete mainGridTarget.dataset.apfCurrentPage;
			mainGridTarget.innerHTML = data.grid_html;
			bindPagination( mainGridTarget, this );
		}

		qsa( '.apf-toggle-badge, [data-apf-badge-for="' + this.key + '"]' ).forEach( function ( badge ) {
			badge.textContent = data.active_count;
			badge.hidden = ! data.active_count;
		} );

		if ( typeof data.result_count_text === 'string' ) {
			var resultCount = qs( '.woocommerce-result-count' );
			if ( resultCount ) {
				resultCount.textContent = data.result_count_text;
			}
		}

		this.setLoading( false );
	};

	var scopes = {};

	function getScope( key ) {
		if ( ! scopes[ key ] ) {
			scopes[ key ] = new Scope( key );
		}
		return scopes[ key ];
	}

	/* ------------------------------------------------------------------ */
	/* URL sync                                                            */
	/* ------------------------------------------------------------------ */

	function syncUrl( params ) {
		if ( ! document.querySelector( '.apf-sidebar[data-url-sync="1"]' ) ) {
			return;
		}

		var url = new URL( window.location.href );
		var keep = [];

		url.searchParams.forEach( function ( value, key ) {
			if ( 0 !== key.indexOf( 'apf_' ) && 'orderby' !== key && 'paged' !== key ) {
				keep.push( [ key, value ] );
			}
		} );

		var next = new URL( url.origin + url.pathname );
		keep.forEach( function ( pair ) {
			next.searchParams.append( pair[0], pair[1] );
		} );

		Object.keys( params ).forEach( function ( key ) {
			if ( 'filter_set_id' === key ) {
				return;
			}
			var value = params[ key ];
			if ( Array.isArray( value ) && value.length ) {
				next.searchParams.set( key, value.join( ',' ) );
			} else if ( ! Array.isArray( value ) && value !== '' && value !== null && value !== undefined ) {
				next.searchParams.set( key, value );
			}
		} );

		window.history.pushState( { apfParams: params }, '', next.toString() );
	}

	window.addEventListener( 'popstate', function ( event ) {
		if ( ! event.state || ! event.state.apfParams ) {
			return;
		}
		Object.keys( scopes ).forEach( function ( key ) {
			scopes[ key ].refresh( {}, false );
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Collapsible sections                                                */
	/* ------------------------------------------------------------------ */

	function bindSections( root ) {
		qsa( '.apf-section-toggle', root ).forEach( function ( button ) {
			if ( button.dataset.apfBound ) {
				return;
			}
			button.dataset.apfBound = '1';

			button.addEventListener( 'click', function () {
				var expanded = button.getAttribute( 'aria-expanded' ) === 'true';
				var panel = document.getElementById( button.getAttribute( 'aria-controls' ) );

				button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( panel ) {
					panel.hidden = expanded;
				}
			} );
		} );

		qsa( '.apf-term-expand', root ).forEach( function ( button ) {
			if ( button.dataset.apfBound ) {
				return;
			}
			button.dataset.apfBound = '1';

			button.addEventListener( 'click', function () {
				var item = button.closest( '.apf-term-item' );
				var children = item.querySelector( ':scope > .apf-term-children' );
				var expanded = item.getAttribute( 'data-expanded' ) === 'true';

				item.setAttribute( 'data-expanded', expanded ? 'false' : 'true' );
				button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( children ) {
					children.hidden = expanded;
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Price slider                                                        */
	/* ------------------------------------------------------------------ */

	function bindPriceSlider( root, scope ) {
		qsa( '.apf-price-slider', root ).forEach( function ( slider ) {
			if ( slider.dataset.apfBound ) {
				return;
			}
			slider.dataset.apfBound = '1';

			var min = slider.querySelector( '.apf-price-input-min' );
			var max = slider.querySelector( '.apf-price-input-max' );
			var fill = slider.querySelector( '.apf-price-slider-range' );
			var labelMin = slider.parentElement.querySelector( '#apf-price-label-min' );
			var labelMax = slider.parentElement.querySelector( '#apf-price-label-max' );
			var bounds = { min: parseFloat( slider.dataset.min ), max: parseFloat( slider.dataset.max ) };

			function paint() {
				var minVal = parseFloat( min.value );
				var maxVal = parseFloat( max.value );
				var range = bounds.max - bounds.min || 1;

				if ( minVal > maxVal ) {
					var swap = minVal;
					minVal = maxVal;
					maxVal = swap;
				}

				var left = ( ( minVal - bounds.min ) / range ) * 100;
				var right = ( ( maxVal - bounds.min ) / range ) * 100;

				fill.style.left = left + '%';
				fill.style.width = Math.max( 0, right - left ) + '%';

				if ( labelMin ) {
					labelMin.textContent = formatPrice( minVal );
				}
				if ( labelMax ) {
					labelMax.textContent = formatPrice( maxVal );
				}
			}

			function formatPrice( value ) {
				var symbol = ( labelMin && labelMin.textContent.match( /^\D+/ ) || [ '' ] )[0];
				return symbol + Math.round( value ).toLocaleString();
			}

			function commit() {
				if ( parseFloat( min.value ) > parseFloat( max.value ) ) {
					var t = min.value;
					min.value = max.value;
					max.value = t;
				}
				triggerChange( scope );
			}

			[ min, max ].forEach( function ( input ) {
				input.addEventListener( 'input', paint );
				input.addEventListener( 'change', commit );
			} );

			paint();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Client-side term search (no network) + AJAX search (network)        */
	/* ------------------------------------------------------------------ */

	function bindTermSearch( root ) {
		qsa( '.apf-term-search-input', root ).forEach( function ( input ) {
			if ( input.dataset.apfBound ) {
				return;
			}
			input.dataset.apfBound = '1';

			input.addEventListener(
				'input',
				debounce( function () {
					var term = input.value.trim().toLowerCase();
					var list = input.closest( '.apf-section-content' ).querySelector( '.apf-term-list' );

					qsa( '.apf-term-item', list ).forEach( function ( item ) {
						var name = ( item.querySelector( '.apf-term-name, .apf-swatch-label, .apf-pill span' ) || {} ).textContent || '';
						item.style.display = ! term || name.toLowerCase().indexOf( term ) !== -1 ? '' : 'none';
					} );
				}, 150 )
			);
		} );
	}

	function bindAjaxSearch( root, scope ) {
		qsa( '.apf-ajax-search', root ).forEach( function ( wrap ) {
			if ( wrap.dataset.apfBound ) {
				return;
			}
			wrap.dataset.apfBound = '1';

			var taxonomy = wrap.dataset.taxonomy;
			var param = wrap.dataset.param;
			var input = wrap.querySelector( '.apf-ajax-search-input' );
			var results = wrap.querySelector( '.apf-ajax-search-results' );
			var pillsWrap = wrap.querySelector( '.apf-selected-pills' );

			scope.ajaxSearchState[ param ] = qsa( '.apf-chip', pillsWrap ).map( function ( chip ) {
				return chip.dataset.value;
			} );

			function renderPills() {
				pillsWrap.innerHTML = '';
				scope.ajaxSearchState[ param ].forEach( function ( value ) {
					var chip = document.createElement( 'span' );
					chip.className = 'apf-chip apf-chip-sm';
					chip.dataset.param = param;
					chip.dataset.value = value;
					chip.innerHTML = value + ' <button type="button" class="apf-chip-remove" aria-label="Remove">×</button>';
					pillsWrap.appendChild( chip );
				} );
			}

			input.addEventListener(
				'input',
				debounce( function () {
					var term = input.value.trim();
					if ( term.length < 2 ) {
						results.hidden = true;
						return;
					}

					fetch( CONFIG.restUrl + '/terms?taxonomy=' + encodeURIComponent( taxonomy ) + '&search=' + encodeURIComponent( term ) )
						.then( function ( response ) {
							return response.json();
						} )
						.then( function ( terms ) {
							results.innerHTML = '';
							terms.forEach( function ( term ) {
								var row = document.createElement( 'div' );
								row.className = 'apf-ajax-search-result';
								row.textContent = term.name + ' (' + term.count + ')';
								row.addEventListener( 'click', function () {
									if ( scope.ajaxSearchState[ param ].indexOf( term.slug ) === -1 ) {
										scope.ajaxSearchState[ param ].push( term.slug );
										renderPills();
										triggerChange( scope );
									}
									results.hidden = true;
									input.value = '';
								} );
								results.appendChild( row );
							} );
							results.hidden = terms.length === 0;
						} );
				}, 300 )
			);

			pillsWrap.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.apf-chip-remove' );
				if ( ! button ) {
					return;
				}
				var chip = button.closest( '.apf-chip' );
				var index = scope.ajaxSearchState[ param ].indexOf( chip.dataset.value );
				if ( index !== -1 ) {
					scope.ajaxSearchState[ param ].splice( index, 1 );
				}
				renderPills();
				triggerChange( scope );
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! wrap.contains( event.target ) ) {
					results.hidden = true;
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Active filter chip removal + reset all                              */
	/* ------------------------------------------------------------------ */

	function bindActiveFilters( root, scope ) {
		var container = qs( '.apf-active-filters', root );
		if ( ! container || container.dataset.apfBound ) {
			return;
		}
		container.dataset.apfBound = '1';

		container.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '.apf-chip-remove' );
			if ( removeButton ) {
				var chip = removeButton.closest( '.apf-chip' );
				removeValue( root, chip.dataset.param, chip.dataset.value );
				triggerChange( scope );
				return;
			}

			if ( event.target.closest( '#apf-reset-all' ) ) {
				resetAll( root, scope );
			}
		} );
	}

	function removeValue( root, param, value ) {
		if ( ! value ) {
			qsa( 'input[name="' + param + '"], select[name="' + param + '"]', root ).forEach( function ( field ) {
				if ( field.type === 'checkbox' || field.type === 'radio' ) {
					field.checked = false;
				} else {
					field.value = '';
				}
			} );

			if ( param === 'apf_price' ) {
				qsa( '.apf-price-slider', root ).forEach( function ( slider ) {
					var min = slider.querySelector( '.apf-price-input-min' );
					var max = slider.querySelector( '.apf-price-input-max' );
					if ( min ) {
						min.value = slider.dataset.min;
					}
					if ( max ) {
						max.value = slider.dataset.max;
					}
				} );
			}
			return;
		}

		qsa( 'input[name="' + param + '[]"], input[name="' + param + '"]', root ).forEach( function ( field ) {
			if ( field.value === value ) {
				field.checked = false;
			}
		} );
	}

	function resetAll( root, scope ) {
		qsa( 'input[type="checkbox"], input[type="radio"]', root ).forEach( function ( field ) {
			field.checked = false;
		} );
		qsa( 'select', root ).forEach( function ( field ) {
			field.value = '';
		} );
		qsa( '.apf-price-slider', root ).forEach( function ( slider ) {
			var min = slider.querySelector( '.apf-price-input-min' );
			var max = slider.querySelector( '.apf-price-input-max' );
			if ( min ) {
				min.value = slider.dataset.min;
			}
			if ( max ) {
				max.value = slider.dataset.max;
			}
		} );

		scope.ajaxSearchState = {};

		triggerChange( scope );
	}

	/* ------------------------------------------------------------------ */
	/* WooCommerce ordering dropdown interception                          */
	/* ------------------------------------------------------------------ */

	function bindOrdering( scope ) {
		var select = qs( 'select[name="orderby"], form.woocommerce-ordering select' );
		if ( ! select || select.dataset.apfBound ) {
			return;
		}
		select.dataset.apfBound = '1';

		select.addEventListener( 'change', function ( event ) {
			var form = select.closest( 'form' );
			if ( form && qs( '#apf-sidebar' ) ) {
				event.preventDefault();
				scope.refresh( { orderby: mapOrderby( select.value ) } );
			}
		} );
	}

	function mapOrderby( wooValue ) {
		var map = {
			menu_order: '',
			popularity: 'popularity',
			rating: 'rating',
			date: 'date',
			price: 'price',
			'price-desc': 'price-desc',
		};
		return map[ wooValue ] !== undefined ? map[ wooValue ] : '';
	}

	/* ------------------------------------------------------------------ */
	/* Pagination + infinite scroll                                        */
	/* ------------------------------------------------------------------ */

	function bindPagination( grid, scope ) {
		if ( grid.dataset.paginationMode === 'infinite' ) {
			bindInfiniteScroll( grid, scope );
			return;
		}

		qsa( 'nav.woocommerce-pagination a, .page-numbers', grid ).forEach( function ( link ) {
			if ( link.dataset.apfBound ) {
				return;
			}
			link.dataset.apfBound = '1';

			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var match = link.href.match( /paged=(\d+)/ ) || link.href.match( /\/page\/(\d+)/ );
				var page = match ? parseInt( match[1], 10 ) : 1;

				scope.refresh( { paged: page } ).then( function () {
					grid.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} );
			} );
		} );
	}

	/**
	 * Infinite-scroll mode: hides the native pagination nav and instead
	 * appends the next page's product list items as an IntersectionObserver
	 * sentinel (placed just after the product grid) comes into view.
	 */
	function bindInfiniteScroll( grid, scope ) {
		var nav = qs( 'nav.woocommerce-pagination', grid );
		var list = qs( 'ul.products', grid );

		if ( ! list ) {
			return;
		}

		var currentPage = parseInt( grid.dataset.apfCurrentPage || '1', 10 );
		var hasMore = !! nav;

		if ( nav ) {
			nav.style.display = 'none';
		}

		var sentinel = grid.querySelector( '.apf-infinite-sentinel' ) || document.createElement( 'div' );
		sentinel.className = 'apf-infinite-sentinel';
		if ( ! sentinel.parentNode ) {
			grid.appendChild( sentinel );
		}

		if ( grid._apfObserver ) {
			grid._apfObserver.disconnect();
		}

		var loading = false;

		var observer = new IntersectionObserver( function ( entries ) {
			if ( ! hasMore || loading || ! entries[0].isIntersecting ) {
				return;
			}

			loading = true;
			var params = scope.serialize();
			params.paged = currentPage + 1;
			if ( scope.filterSetId ) {
				params.filter_set_id = scope.filterSetId;
			}

			var query = buildQuery( params );

			fetch( CONFIG.restUrl + '/filter' + ( query ? '?' + query : '' ), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': CONFIG.nonce },
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					var temp = document.createElement( 'div' );
					temp.innerHTML = data.grid_html || '';

					qsa( 'ul.products > li', temp ).forEach( function ( item ) {
						list.appendChild( item );
					} );

					currentPage = data.page || currentPage + 1;
					grid.dataset.apfCurrentPage = String( currentPage );
					hasMore = currentPage < ( data.max_num_pages || currentPage );
					loading = false;

					if ( ! hasMore ) {
						observer.disconnect();
						sentinel.remove();
					}
				} )
				.catch( function () {
					loading = false;
				} );
		}, { rootMargin: '400px' } );

		grid._apfObserver = observer;
		observer.observe( sentinel );
	}

	function triggerChange( scope ) {
		var instant = ! scope.sidebar || scope.sidebar.dataset.instant !== '0';

		if ( instant ) {
			scope.refresh();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Offcanvas (mobile)                                                   */
	/* ------------------------------------------------------------------ */

	function bindOffcanvas() {
		var overlay = qs( '#apf-overlay' );

		function openSidebar() {
			var sidebar = qs( '#apf-sidebar' );
			if ( ! sidebar ) {
				return;
			}
			sidebar.classList.add( 'apf-open' );
			if ( overlay ) {
				overlay.hidden = false;
			}
			document.body.style.overflow = 'hidden';
			qsa( '#apf-toggle-sidebar, .apf-toggle-button' ).forEach( function ( button ) {
				button.setAttribute( 'aria-expanded', 'true' );
			} );
		}

		function closeSidebar() {
			var sidebar = qs( '#apf-sidebar' );
			if ( sidebar ) {
				sidebar.classList.remove( 'apf-open' );
			}
			if ( overlay ) {
				overlay.hidden = true;
			}
			document.body.style.overflow = '';
			qsa( '#apf-toggle-sidebar, .apf-toggle-button' ).forEach( function ( button ) {
				button.setAttribute( 'aria-expanded', 'false' );
			} );
		}

		// Delegated at the document level (rather than bound directly to
		// the button) because the toggle button lives inside the AJAX
		// grid wrapper and gets replaced with fresh markup on every
		// filter change.
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '#apf-toggle-sidebar, .apf-toggle-button' ) ) {
				openSidebar();
				return;
			}

			if ( event.target.closest( '#apf-close-offcanvas' ) || event.target === overlay ) {
				closeSidebar();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				closeSidebar();
			}
		} );

		// Basic swipe-to-close support on touch devices.
		var touchStartX = null;
		document.addEventListener( 'touchstart', function ( event ) {
			var sidebar = qs( '#apf-sidebar.apf-open' );
			if ( sidebar && sidebar.contains( event.target ) ) {
				touchStartX = event.touches[0].clientX;
			}
		} );
		document.addEventListener( 'touchend', function ( event ) {
			if ( touchStartX === null ) {
				return;
			}
			var deltaX = event.changedTouches[0].clientX - touchStartX;
			var sidebar = qs( '#apf-sidebar.apf-open' );
			if ( sidebar ) {
				var isRight = sidebar.classList.contains( 'apf-offcanvas-right' );
				if ( ( isRight && deltaX > 60 ) || ( ! isRight && deltaX < -60 ) ) {
					closeSidebar();
				}
			}
			touchStartX = null;
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Binding entry points                                                 */
	/* ------------------------------------------------------------------ */

	function bindSidebar( sidebar, scope ) {
		scope.addSidebar( sidebar );
		bindSections( sidebar );
		bindPriceSlider( sidebar, scope );
		bindTermSearch( sidebar );
		bindAjaxSearch( sidebar, scope );
		bindActiveFilters( sidebar, scope );
		bindFormChanges( sidebar, scope );
	}

	function bindStandalone( widget, scope ) {
		scope.addStandaloneWidget( widget );
		bindSections( widget );
		bindPriceSlider( widget, scope );
		bindTermSearch( widget );
		bindAjaxSearch( widget, scope );
		bindActiveFilters( widget, scope );
		bindFormChanges( widget, scope );
	}

	function bindFormChanges( root, scope ) {
		root.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( 'input[type="checkbox"], input[type="radio"], select' ) ) {
				triggerChange( scope );
			}
		} );
	}

	function init() {
		var sidebar = qs( '#apf-sidebar' );
		var contextScope = getScope( 'context' );

		if ( sidebar ) {
			bindSidebar( sidebar, contextScope );
		}

		qsa( '.apf-standalone-widget' ).forEach( function ( widget ) {
			var key = widget.dataset.apfScope || 'context';
			bindStandalone( widget, getScope( key ) );
		} );

		qsa( '.apf-standalone-grid' ).forEach( function ( grid ) {
			var key = grid.dataset.apfScope || 'context';
			getScope( key ).addGrid( grid );
			bindPagination( grid, getScope( key ) );
		} );

		var mainGridTarget = qs( '#apf-ajax-grid-target' );
		if ( mainGridTarget ) {
			bindPagination( mainGridTarget, contextScope );
		}

		bindOrdering( contextScope );
		bindOffcanvas();

		qs( '#apf-apply-filters' ) && qs( '#apf-apply-filters' ).addEventListener( 'click', function () {
			contextScope.refresh();
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
