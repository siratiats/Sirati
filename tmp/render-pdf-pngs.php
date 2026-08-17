<?php

foreach (glob(__DIR__.'/pdfs/*.pdf') ?: [] as $pdfPath) {
    $image = new Imagick;
    $image->setResolution(144, 144);
    $image->readImage($pdfPath.'[0]');
    $image->setImageFormat('png');
    $image->writeImage(__DIR__.'/pdfs/'.pathinfo($pdfPath, PATHINFO_FILENAME).'.png');
    $image->clear();
}
