<?php
/**
 * Verein & Design: Name, Farben, Logo, Hero-Fotos, Mail-Absender, Datenschutz-Kontakt.
 * Aktionen (POST action=…): save, upload_logo, remove_logo, upload_hero, remove_hero
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Domain\SettingsValidator;
use App\Http\Admin;
use App\Service\ImageUpload;

const MAX_HERO_IMAGES = 12;

$ctx = Admin::boot();
$errors = [];
$old = Settings::all();
$heroImages = array_values(array_filter(json_decode((string) Settings::get('hero_images', '[]'), true) ?: [], 'is_string'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'save':
                $result = SettingsValidator::validate($_POST);
                if ($result['errors'] === []) {
                    foreach ($result['values'] as $key => $value) {
                        Settings::set($key, $value);
                    }
                    Session::flash('success', 'Einstellungen gespeichert.');
                    redirect('admin/einstellungen.php');
                }
                $errors = $result['errors'];
                $old = $result['values'] + $old;
                break;

            case 'upload_logo':
                $new = ImageUpload::fromRequest($_FILES['logo'] ?? [], ImageUpload::KIND_LOGO);
                ImageUpload::delete((string) Settings::get('logo_file'));
                Settings::set('logo_file', $new);
                Session::flash('success', 'Logo gespeichert.');
                redirect('admin/einstellungen.php');

            case 'remove_logo':
                ImageUpload::delete((string) Settings::get('logo_file'));
                Settings::set('logo_file', '');
                Session::flash('success', 'Logo entfernt – es wird wieder der Platzhalter angezeigt.');
                redirect('admin/einstellungen.php');

            case 'upload_hero':
                $files = $_FILES['hero'] ?? [];
                $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
                $saved = 0;
                $problems = [];
                for ($i = 0; $i < $count; $i++) {
                    if (count($heroImages) >= MAX_HERO_IMAGES) {
                        $problems[] = 'Maximal ' . MAX_HERO_IMAGES . ' Fotos – weitere wurden nicht übernommen.';
                        break;
                    }
                    $single = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                    try {
                        $heroImages[] = ImageUpload::fromRequest($single, ImageUpload::KIND_HERO);
                        $saved++;
                    } catch (RuntimeException $e) {
                        $problems[] = $files['name'][$i] . ': ' . $e->getMessage();
                    }
                }
                Settings::set('hero_images', json_encode($heroImages));
                if ($saved > 0) {
                    Session::flash('success', $saved === 1 ? '1 Foto hinzugefügt.' : $saved . ' Fotos hinzugefügt.');
                }
                foreach ($problems as $problem) {
                    Session::flash('danger', $problem);
                }
                if ($saved === 0 && $problems === []) {
                    Session::flash('warning', 'Bitte mindestens ein Foto auswählen.');
                }
                redirect('admin/einstellungen.php');

            case 'remove_hero':
                $file = (string) ($_POST['file'] ?? '');
                if (in_array($file, $heroImages, true)) {
                    ImageUpload::delete($file);
                    Settings::set('hero_images', json_encode(array_values(array_diff($heroImages, [$file]))));
                    Session::flash('success', 'Foto entfernt.');
                }
                redirect('admin/einstellungen.php');
        }
    } catch (RuntimeException $e) {
        Session::flash('danger', $e->getMessage());
        redirect('admin/einstellungen.php');
    }
}

View::render('admin/settings', Admin::layoutVars($ctx, 'settings', 'Verein & Design') + [
    'errors' => $errors,
    'old' => $old,
    'heroImages' => $heroImages,
    'maxHero' => MAX_HERO_IMAGES,
    'uploadLimit' => (string) ini_get('upload_max_filesize'),
    'scripts' => ['assets/js/image-resize.js', 'assets/js/theme-preview.js'],
], 'layout-admin');
