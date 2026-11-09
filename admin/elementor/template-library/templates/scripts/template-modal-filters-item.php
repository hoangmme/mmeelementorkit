<?php
/**
 * Template Library Filter Item.
 */
?>
<label class="emselementor-template-filter-label">
    <input type="radio" value="{{ slug }}" <# if ( '' === slug ) { #> checked<# } #> name="emselementor-template-filter">
    <span>{{ title.replace('&amp;', '&') }}</span>
</label>