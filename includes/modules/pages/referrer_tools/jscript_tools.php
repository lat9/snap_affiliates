<script type="text/javascript"><!--
var referrerPrefix = "<?php echo SNAP_KEY_PREFIX; ?>";
var referrerKey = "<?php echo $referrer_key; ?>";
var link_hint = "<?php echo TEXT_PASTE_LINK_HERE; ?>";

function transform() 
{
    var textArea = document.getElementById("referrerLinkDump");
    var linkPrefix = '&';

    if (textArea) {
        if (textArea.value.indexOf("&referrer=" + referrerPrefix ) == -1 && textArea.value.indexOf("?referrer=" + referrerPrefix) == -1 && textArea.value != link_hint) {
            if (textArea.value.indexOf('?') == -1) {
                linkPrefix = '?';
            }
            if (textArea.value.indexOf("&") == -1 && textArea.value.indexOf("?") == -1 && textArea.value.charAt(textArea.value.length-1) != "/")  {
                textArea.value += "/";
            }
            textArea.value += linkPrefix + "referrer=" + referrerKey;
        }
    }
}

function removeTip(textArea) 
{
    if (textArea.value == link_hint) {
        textArea.style.color = "black";
        textArea.value = "";
    }
}

function addTipIfBlank(textArea) 
{
    if (textArea.value == "") {
        textArea.value = link_hint;
        textArea.style.color = "grey";
    }
}
//--></script>