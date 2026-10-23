/* Plogins Assets - add/remove repeatable rule rows. No build step, no deps. */
(function () {
	'use strict';

	var rows = document.getElementById( 'plogins-assets-rows' );
	var tpl = document.getElementById( 'plogins-assets-template' );
	var addBtn = document.getElementById( 'plogins-assets-add' );

	if ( ! rows || ! tpl || ! addBtn ) {
		return;
	}

	var counter = rows.querySelectorAll( '.plogins-assets-row' ).length;

	addBtn.addEventListener( 'click', function () {
		var html = tpl.innerHTML.replace( /__INDEX__/g, String( counter++ ) );
		var tmp = document.createElement( 'tbody' );
		tmp.innerHTML = html.trim();
		var row = tmp.querySelector( '.plogins-assets-row' );
		if ( row ) {
			rows.appendChild( row );
		}
	} );

	// Only these two conditions look at the ids on the front end, so the field
	// follows the select instead of sitting open and pretending to narrow a rule
	// it can never narrow. Keep in sync with SettingsRepository::ID_CONDITIONS.
	var idConditions = [ 'is_singular', 'not_singular' ];

	rows.addEventListener( 'change', function ( e ) {
		var select = e.target.closest( 'select[name$="[condition]"]' );
		if ( ! select ) {
			return;
		}
		var row = select.closest( '.plogins-assets-row' );
		if ( ! row ) {
			return;
		}
		var uses = idConditions.indexOf( select.value ) !== -1;
		var input = row.querySelector( 'input[name$="[ids]"]' );
		var note = row.querySelector( '.plogins-assets-ids-note' );
		if ( input ) {
			input.disabled = ! uses;
		}
		if ( note ) {
			note.hidden = uses;
		}
		var postType = row.querySelector( 'select[name$="[post_type]"]' );
		if ( postType ) {
			postType.disabled = select.value !== 'single_post_type';
		}
	} );

	rows.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.plogins-assets-remove' );
		if ( ! btn ) {
			return;
		}
		var row = btn.closest( '.plogins-assets-row' );
		if ( row && rows.querySelectorAll( '.plogins-assets-row' ).length > 1 ) {
			row.remove();
		} else if ( row ) {
			// Keep at least one row; just clear the handle field.
			var handle = row.querySelector( 'input[name$="[handle]"]' );
			if ( handle ) {
				handle.value = '';
			}
		}
	} );
})();
