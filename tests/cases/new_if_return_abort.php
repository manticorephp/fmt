<?php
function a($items) {
    foreach ($items as $i) {
        if ($i->ok()) return true;
    }
    return false;
}
