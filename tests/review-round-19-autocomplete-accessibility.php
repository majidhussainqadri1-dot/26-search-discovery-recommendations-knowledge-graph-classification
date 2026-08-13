<?php
/** Round 19 regression: autocomplete rejects stale responses and supports listbox keyboard interaction. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/assets/js/file26.js' );
$checks = array(
	"requestSequence += 1" => 'input changes invalidate prior requests',
	"sequence !== requestSequence || input.value.trim() !== value" => 'stale suggestion response cannot render',
	"event.key === 'ArrowDown'" => 'ArrowDown listbox navigation',
	"event.key === 'ArrowUp'" => 'ArrowUp listbox navigation',
	"event.key === 'Enter'" => 'Enter activates current suggestion',
	"aria-activedescendant" => 'active option is exposed to assistive technology',
	"aria-selected" => 'listbox selection state is explicit',
	"aria-autocomplete" => 'autocomplete semantics are explicit',
);
$failures=0;foreach($checks as $needle=>$label){if(false===strpos($source,$needle)){fwrite(STDERR,"FAIL: $label\n");$failures++;}}if($failures){exit(1);}echo "Round 19 autocomplete accessibility regression passed.\n";
