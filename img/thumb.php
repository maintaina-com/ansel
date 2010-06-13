<?php
/**
 * Copyright 2003-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/../lib/Application.php';
Horde_Registry::appInit('ansel');

$image = $GLOBALS['injector']->getInstance('Ansel_Storage')->getScope()->getImage(Horde_Util::getFormData('image'));
$gallery = $GLOBALS['injector']->getInstance('Ansel_Storage')->getScope()->getGallery(abs($image->gallery));
if (!$gallery->hasPermission($registry->getAuth(), Horde_Perms::READ)) {
    throw new Horde_Exception_PermissionDenied(_("Access denied viewing this photo."));
}

/* Sendfile support. Lighttpd < 1.5 only understands the X-LIGHTTPD-send-file header */
if ($conf['vfs']['src'] == 'sendfile') {
    /* Need to ensure the file exists */
    try {
        $image->createView('thumb', 'ansel_default');
    } catch (Horde_Exception $e) {
        Horde::logMessage($result, 'ERR');
        exit;
    }
    $filename = $injector->getInstance('Horde_Vfs')->getVfs('images')->readFile($image->getVFSPath('thumb'), $image->getVFSName('thumb'));
    header('Content-Type: ' . $image->getType('thumb'));
    header('X-LIGHTTPD-send-file: ' . $filename);
    header('X-Sendfile: ' . $filename);
    exit;
}

$image->display('thumb');
