/**
 * MAB Commerce Filters — front-end AJAX engine (vanilla JS, no jQuery).
 */
( function () {
	'use strict';

	if ( typeof window.mabcfSettings === 'undefined' ) {
		return;
	}

	var SETTINGS = window.mabcfSettings;
	var debounceTimers = {};

	function debounce( key, fn, delay ) {
		clearTimeout( debounceTimers[ key ] );
		debounceTimers[ key ] = setTimeout( fn, delay );
	}

	function getFormSettings( form ) {
		try {
			return JSON.parse( form.getAttribute( 'data-settings' ) || '{}' );
		} catch ( e ) {
			return {};
		}
	}

	function findTarget( wrapper, settings ) {
		var selector = settings.target || '.mabcf-products-target';
		var scoped = document.querySelectorAll( selector + '[data-filter-set-id="' + settings.filterSetId + '"]' );

		if ( scoped.length ) {
			return scoped[ 0 ];
		}

		return document.querySelector( selector );
	}

	function setLoading( wrapper, loading ) {
		wrapper.classList.toggle( 'is-loading', loading );
	}

	/**
	 * Sends the current form state to the AJAX endpoint and swaps in the
	 * returned facets, active-filters bar and product grid.
	 */
	function runFilter( wrapper, form, extra ) {
		var settings = getFormSettings( form );
		var target = findTarget( wrapper, settings );

		var body = new FormData( form );
		body.set( 'action', 'mabcf_filter_products' );
		body.set( 'nonce', SETTINGS.nonce );
		body.set( 'filter_set_id', settings.filterSetId );
		body.set( 'context_type', settings.contextType || 'global' );
		body.set( 'context_taxonomy', settings.contextTaxonomy || '' );
		body.set( 'context_value', settings.contextValue || '' );
		body.set( 'paged', ( extra && extra.paged ) || 1 );
		body.set( 'orderby', ( extra && extra.orderby ) || currentOrderby( target ) );

		setLoading( wrapper, true );

		fetch( SETTINGS.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					return;
				}

				var data = json.data;

				var filtersEl = wrapper.querySelector( '.mabcf-filters' );
				if ( filtersEl && data.filters_html ) {
					filtersEl.innerHTML = data.filters_html;
				}

				var activeEl = wrapper.querySelector( '.mabcf-active' );
				if ( activeEl && typeof data.active_html === 'string' ) {
					activeEl.outerHTML = data.active_html;
				}

				if ( target && typeof data.grid_html === 'string' ) {
					if ( extra && extra.append ) {
						appendGrid( target, data.grid_html );
					} else {
						target.innerHTML = data.grid_html;
					}
				}

				if ( settings.change_url && data.query_args ) {
					updateUrl( data.query_args );
				}

				wrapper.dispatchEvent( new CustomEvent( 'mabcf:filtered', { detail: data, bubbles: true } ) );
			} )
			['catch']( function () {} )
			.then( function () {
				setLoading( wrapper, false );
			} );
	}

	function currentOrderby( target ) {
		if ( ! target ) {
			return 'menu_order';
		}
		var select = target.querySelector( '.mabcf-toolbar__orderby' );
		return select ? select.value : 'menu_order';
	}

	function appendGrid( target, html ) {
		var temp = document.createElement( 'div' );
		temp.innerHTML = html;

		var newList = temp.querySelector( '.mabcf-grid' );
		var existingList = target.querySelector( '.mabcf-grid' );

		if ( newList && existingList ) {
			while ( newList.firstElementChild ) {
				existingList.appendChild( newList.firstElementChild );
			}
		}

		var newScroll = temp.querySelector( '.mabcf-infinite-scroll' );
		var oldScroll = target.querySelector( '.mabcf-infinite-scroll' );

		if ( oldScroll ) {
			oldScroll.remove();
		}

		if ( newScroll ) {
			target.appendChild( newScroll );
			observeInfiniteScroll( newScroll );
		}
	}

	function updateUrl( queryArgs ) {
		var url = new URL( window.location.href );
		var params = url.searchParams;

		Array.prototype.slice.call( params.keys() ).forEach( function ( key ) {
			if ( 'paged' !== key && 'page' !== key ) {
				params.delete( key );
			}
		} );

		Object.keys( queryArgs ).forEach( function ( key ) {
			params.set( key, queryArgs[ key ] );
		} );

		window.history.pushState( { mabcf: true }, '', url.toString() );
	}

	/* ---------- Accordion ---------- */

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '.mabcf-filter__toggle' );
		if ( toggle ) {
			var expanded = 'true' === toggle.getAttribute( 'aria-expanded' );
			toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
		}
	} );

	/* ---------- Show more / less ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mabcf-show-more' );
		if ( ! btn ) {
			return;
		}
		var scroller = btn.previousElementSibling;
		if ( scroller ) {
			scroller.classList.toggle( 'is-expanded' );
			btn.textContent = scroller.classList.contains( 'is-expanded' ) ? SETTINGS.i18n.seeLess : SETTINGS.i18n.seeMore;
		}
	} );

	/* ---------- Swatch active state ---------- */

	document.addEventListener( 'change', function ( e ) {
		var swatch = e.target.closest( '.mabcf-swatch' );
		if ( swatch ) {
			swatch.classList.toggle( 'mabcf-swatch--active', e.target.checked );
		}
	} );

	/* ---------- Instant filtering on checkbox/radio/select/toggle change ---------- */

	document.addEventListener( 'change', function ( e ) {
		var form = e.target.closest( '.mabcf-form' );
		if ( ! form ) {
			return;
		}

		if ( e.target.matches( '.mabcf-search__input, .mabcf-price-slider__input-min, .mabcf-price-slider__input-max' ) ) {
			return;
		}

		var settings = getFormSettings( form );
		if ( settings.instant ) {
			var wrapper = form.closest( '.mabcf' );
			runFilter( wrapper, form );
		}
	} );

	/* ---------- Search input (debounced) ---------- */

	document.addEventListener( 'input', function ( e ) {
		if ( ! e.target.matches( '.mabcf-search__input' ) ) {
			return;
		}
		var form = e.target.closest( '.mabcf-form' );
		if ( ! form ) {
			return;
		}
		var wrapper = form.closest( '.mabcf' );
		debounce( 'search', function () {
			runFilter( wrapper, form );
		}, 450 );
	} );

	/* ---------- Form submit (Apply button / no-JS fallback) ---------- */

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.mabcf-form' );
		if ( ! form ) {
			return;
		}
		var settings = getFormSettings( form );
		if ( settings.ajax ) {
			e.preventDefault();
			runFilter( form.closest( '.mabcf' ), form );
		}
	} );

	/* ---------- Reset / Clear All ---------- */

	document.addEventListener( 'click', function ( e ) {
		var resetBtn = e.target.closest( '.mabcf-reset, .mabcf-clear-all' );
		if ( ! resetBtn ) {
			return;
		}

		var wrapper = resetBtn.closest( '.mabcf' ) || document.querySelector( '.mabcf' );
		if ( ! wrapper ) {
			return;
		}
		var form = wrapper.querySelector( '.mabcf-form' );
		if ( ! form ) {
			return;
		}

		e.preventDefault();
		form.reset();
		form.querySelectorAll( 'input[type="checkbox"], input[type="radio"]' ).forEach( function ( input ) {
			input.checked = false;
		} );
		form.querySelectorAll( 'input[type="text"], input[type="search"], input[type="number"], input[type="date"]' ).forEach( function ( input ) {
			input.value = '';
		} );
		form.querySelectorAll( '.mabcf-swatch--active' ).forEach( function ( el ) {
			el.classList.remove( 'mabcf-swatch--active' );
		} );

		runFilter( wrapper, form );
	} );

	/* ---------- Remove a single active-filter pill ---------- */

	document.addEventListener( 'click', function ( e ) {
		var removeBtn = e.target.closest( '.mabcf-pill__remove' );
		if ( ! removeBtn ) {
			return;
		}

		var pill = removeBtn.closest( '.mabcf-pill' );
		var wrapper = pill.closest( '.mabcf' ) || document.querySelector( '.mabcf' );
		var form = wrapper ? wrapper.querySelector( '.mabcf-form' ) : null;
		if ( ! form ) {
			return;
		}

		var filterId = pill.getAttribute( 'data-filter-id' );
		var value = pill.getAttribute( 'data-value' );

		form.querySelectorAll( '[name^="mabcf_filter[' + filterId + ']"]' ).forEach( function ( input ) {
			if ( 'checkbox' === input.type || 'radio' === input.type ) {
				if ( input.value === value ) {
					input.checked = false;
				}
			} else {
				input.value = '';
			}
		} );

		runFilter( wrapper, form );
	} );

	/* ---------- Price slider (dual handle, drag + keyboard) ---------- */

	function initPriceSliders( scope ) {
		( scope || document ).querySelectorAll( '.mabcf-price-slider' ).forEach( function ( slider ) {
			if ( slider.dataset.mabcfInit ) {
				return;
			}
			slider.dataset.mabcfInit = '1';

			var min = parseFloat( slider.dataset.min );
			var max = parseFloat( slider.dataset.max );
			var step = parseFloat( slider.dataset.step ) || 1;
			var track = slider.querySelector( '.mabcf-price-slider__track' );
			var range = slider.querySelector( '.mabcf-price-slider__range' );
			var handleMin = slider.querySelector( '.mabcf-price-slider__handle--min' );
			var handleMax = slider.querySelector( '.mabcf-price-slider__handle--max' );
			var inputMin = slider.querySelector( '.mabcf-price-slider__input-min' );
			var inputMax = slider.querySelector( '.mabcf-price-slider__input-max' );
			var fromLabel = slider.querySelector( '.mabcf-price-slider__from' );
			var toLabel = slider.querySelector( '.mabcf-price-slider__to' );
			var applyBtn = slider.querySelector( '.mabcf-price-slider__apply' );

			var current = {
				min: parseFloat( slider.dataset.currentMin ) || min,
				max: parseFloat( slider.dataset.currentMax ) || max,
			};

			function pctFor( value ) {
				if ( max === min ) {
					return 0;
				}
				return ( ( value - min ) / ( max - min ) ) * 100;
			}

			function paint() {
				var minPct = pctFor( current.min );
				var maxPct = pctFor( current.max );
				handleMin.style.left = minPct + '%';
				handleMax.style.left = maxPct + '%';
				range.style.left = minPct + '%';
				range.style.width = Math.max( 0, maxPct - minPct ) + '%';
				inputMin.value = current.min;
				inputMax.value = current.max;
				if ( fromLabel ) {
					fromLabel.textContent = fromLabel.textContent.replace( /[\d.]+$/, current.min );
				}
				if ( toLabel ) {
					toLabel.textContent = toLabel.textContent.replace( /[\d.]+$/, current.max );
				}
				handleMin.setAttribute( 'aria-valuenow', current.min );
				handleMax.setAttribute( 'aria-valuenow', current.max );
			}

			function valueFromClientX( clientX ) {
				var rect = track.getBoundingClientRect();
				var pct = Math.min( 1, Math.max( 0, ( clientX - rect.left ) / rect.width ) );
				var raw = min + pct * ( max - min );
				return Math.round( raw / step ) * step;
			}

			function commit( silent ) {
				var wrapper = slider.closest( '.mabcf' );
				var form = slider.closest( '.mabcf-form' );
				var settings = form ? getFormSettings( form ) : {};
				if ( ! silent && settings.instant && ! applyBtn ) {
					runFilter( wrapper, form );
				}
			}

			function startDrag( handle, isMin ) {
				return function ( startEvent ) {
					startEvent.preventDefault();

					function move( moveEvent ) {
						var clientX = moveEvent.touches ? moveEvent.touches[ 0 ].clientX : moveEvent.clientX;
						var value = valueFromClientX( clientX );

						if ( isMin ) {
							current.min = Math.min( value, current.max );
						} else {
							current.max = Math.max( value, current.min );
						}
						paint();
					}

					function up() {
						document.removeEventListener( 'mousemove', move );
						document.removeEventListener( 'mouseup', up );
						document.removeEventListener( 'touchmove', move );
						document.removeEventListener( 'touchend', up );
						commit();
					}

					document.addEventListener( 'mousemove', move );
					document.addEventListener( 'mouseup', up );
					document.addEventListener( 'touchmove', move, { passive: false } );
					document.addEventListener( 'touchend', up );
				};
			}

			handleMin.addEventListener( 'mousedown', startDrag( handleMin, true ) );
			handleMin.addEventListener( 'touchstart', startDrag( handleMin, true ), { passive: false } );
			handleMax.addEventListener( 'mousedown', startDrag( handleMax, false ) );
			handleMax.addEventListener( 'touchstart', startDrag( handleMax, false ), { passive: false } );

			[ [ handleMin, true ], [ handleMax, false ] ].forEach( function ( pair ) {
				pair[ 0 ].addEventListener( 'keydown', function ( keyEvent ) {
					var delta = 0;
					if ( 'ArrowLeft' === keyEvent.key || 'ArrowDown' === keyEvent.key ) {
						delta = -step;
					} else if ( 'ArrowRight' === keyEvent.key || 'ArrowUp' === keyEvent.key ) {
						delta = step;
					} else {
						return;
					}
					keyEvent.preventDefault();
					if ( pair[ 1 ] ) {
						current.min = Math.min( Math.max( min, current.min + delta ), current.max );
					} else {
						current.max = Math.max( Math.min( max, current.max + delta ), current.min );
					}
					paint();
					commit();
				} );
			} );

			if ( applyBtn ) {
				applyBtn.addEventListener( 'click', function () {
					commit( false );
				} );
			}

			paint();
		} );
	}

	/* ---------- Offcanvas / mobile drawer ---------- */

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '.mabcf-offcanvas-toggle' );
		if ( ! toggle ) {
			return;
		}
		var wrapper = toggle.closest( '.mabcf' ) || document.querySelector( '.mabcf--offcanvas' );
		if ( wrapper ) {
			var isOpen = wrapper.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var wrapper = document.querySelector( '.mabcf--offcanvas.is-open' );
		if ( wrapper && e.target === wrapper ) {
			wrapper.classList.remove( 'is-open' );
		}
	} );

	/* ---------- Pagination (AJAX intercept) ---------- */

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( '.mabcf-pagination a' );
		if ( ! link ) {
			return;
		}
		var target = link.closest( '.mabcf-products-target' );
		var wrapper = document.querySelector( '.mabcf[data-filter-set-id="' + ( target ? target.getAttribute( 'data-filter-set-id' ) : '' ) + '"]' ) || document.querySelector( '.mabcf' );
		var form = wrapper ? wrapper.querySelector( '.mabcf-form' ) : null;
		if ( ! form ) {
			return;
		}

		e.preventDefault();
		var url = new URL( link.href );
		var paged = url.searchParams.get( 'paged' ) || url.searchParams.get( 'page' ) || 1;
		runFilter( wrapper, form, { paged: paged } );
		window.scrollTo( { top: target ? target.getBoundingClientRect().top + window.scrollY - 100 : 0, behavior: 'smooth' } );
	} );

	/* ---------- Sorting dropdown ---------- */

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '.mabcf-toolbar__orderby' ) ) {
			return;
		}
		var target = e.target.closest( '.mabcf-products-target' );
		var setId = target ? target.getAttribute( 'data-filter-set-id' ) : null;
		var wrapper = setId ? document.querySelector( '.mabcf[data-filter-set-id="' + setId + '"]' ) : document.querySelector( '.mabcf' );
		var form = wrapper ? wrapper.querySelector( '.mabcf-form' ) : null;
		if ( form ) {
			runFilter( wrapper, form, { orderby: e.target.value } );
		}
	} );

	/* ---------- Infinite scroll ---------- */

	function observeInfiniteScroll( sentinel ) {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				observer.disconnect();

				var target = sentinel.closest( '.mabcf-products-target' );
				var setId = target ? target.getAttribute( 'data-filter-set-id' ) : null;
				var wrapper = setId ? document.querySelector( '.mabcf[data-filter-set-id="' + setId + '"]' ) : document.querySelector( '.mabcf' );
				var form = wrapper ? wrapper.querySelector( '.mabcf-form' ) : null;
				var nextPage = parseInt( sentinel.dataset.page, 10 ) + 1;

				if ( form && nextPage <= parseInt( sentinel.dataset.maxPages, 10 ) ) {
					sentinel.dataset.page = nextPage;
					runFilter( wrapper, form, { paged: nextPage, append: true } );
				}
			} );
		} );

		observer.observe( sentinel );
	}

	/* ---------- Init ---------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initPriceSliders( document );
		document.querySelectorAll( '.mabcf-infinite-scroll' ).forEach( observeInfiniteScroll );
	} );

	document.addEventListener( 'mabcf:filtered', function () {
		initPriceSliders( document );
	} );

	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
} )();
