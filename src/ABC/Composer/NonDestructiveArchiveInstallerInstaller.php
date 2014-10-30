<?php

namespace ABC\Composer;

use Composer\Util\RemoteFilesystem;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Factory;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

/**
 * This class is in charge of handling the installation of an external package
 * that will be downloaded.
 *
 * @author David Négrier
 * @author Aaron Latham-Ilari
 */
class NonDestructiveArchiveInstallerInstaller extends LibraryInstaller {

    protected $rfs;
    protected $debug = false;

    /**
     * Initializes library installer.
     *
     * @param IOInterface $io
     * @param Composer    $composer
     * @param string      $type
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library') {
        parent::__construct($io, $composer, $type);
        $this->rfs = new RemoteFilesystem($io);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $package) {

        if (!$repo->hasPackage($initial))
            throw new \InvalidArgumentException('Package is not installed: ' . $initial);

        // Debug
        $this->debug = $this->isDebug($package);

        // Composer stuff
        $this->initializeVendorDir();
        $this->removeBinaries($initial);
        $repo->removePackage($initial);

        if (!$repo->hasPackage($package)) $repo->addPackage(clone $package);

        $this->downloadAndExtractFile($package);
    }

    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package) {
        if ($this->alwaysInstall($package)) return false;
        return parent::isInstalled($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package) {

        // Debug
        $this->debug = $this->isDebug($package);

        // Composer stuff
        $this->initializeVendorDir();

        $downloadPath = $this->getInstallPath($package);

        if (!is_readable($downloadPath) && $repo->hasPackage($package)) $this->removeBinaries($package);
        if (!$repo->hasPackage($package)) $repo->addPackage(clone $package);

        $this->downloadAndExtractFile($package);
    }

    protected function alwaysInstall(PackageInterface $package) {
        $p_extra = $package->getExtra();
        return isset($p_extra['always-install']) && strtolower($p_extra['always-install']) == "false"
            ? false
            : true;
    }

    protected function isDebug(PackageInterface $package) {
        $p_extra = $package->getExtra();
        return isset($p_extra['debug']) && strtolower($p_extra['debug']) == "true"
            ? true
            : false;
    }

    /**
     * Downloads and extracts the package, only if the URL to download has not been downloaded before.
     *
     * @param PackageInterface $package
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    private function downloadAndExtractFile(PackageInterface $package) {

        // get extra data
        $c_extra = $this->composer->getPackage()->getExtra();
        $p_extra = $package->getExtra();

        $url = $package->getDistUrl();

        if ($url) {

            // handle package level config
            // ---------------------------

            $omitFirstDirectory = isset($p_extra['omit-first-directory'])
                ? strtolower($p_extra['omit-first-directory']) == "true"
                : false;

            $targetDir = isset($p_extra['target-dir'])
                ? realpath('./' . trim($p_extra['target-dir'], '/')) . '/'
                : $this->getInstallPath($package);

            // handle overrides
            // ---------------------------

            if (isset($c_extra['installer-paths'])) {
                foreach ($c_extra['installer-paths'] as $path => $pkgs) {
                    foreach ($pkgs as $pkg) {
                        if ($pkg == $package->getName()) {
                            $targetDir = realpath('./' . trim($path, '/')) . '/';
                        }
                    }
                }
            }

            // Has archive has been downloaded
            if (self::getLastDownloadedFileUrl($package, $this->vendorDir) == $url && !$this->alwaysInstall($package)) return;

            // SSL Check
            if (!extension_loaded('openssl') && 0 === strpos($url, 'https:'))
                throw new \RuntimeException('You must enable the openssl extension to download files via https');

            // Extract some data about our download
            $fileName  = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

            // Download
            $this->io->write("  - Downloading <info>" . $fileName . "</info> from <info>".$url."</info>");
            $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

            // Check
            if (!file_exists($fileName))
                throw new \UnexpectedValueException($url.' could not be saved to ' . $fileName . ', make sure the'
                .' directory is writable and you have internet connectivity');

            // Extract using ZIP downloader
            if ($extension == 'zip') {
                $this->io->write("    Extracting <info>" . $fileName . "</info> to <info>" . $targetDir . "</info>\n");
                $this->extractZip($fileName, $targetDir, $omitFirstDirectory);
            } elseif ($extension == 'tar' || $extension == 'gz' || $extension == 'bz2') {
                $this->io->write("    Extracting <info>" . $fileName . "</info> to <info>" . $targetDir . "</info>\n");
                $this->extractTgz($fileName, $targetDir, $omitFirstDirectory);
            }

            // Delete archive once download is performed
            unlink($fileName);

            // Save last download URL
            self::setLastDownloadedFileUrl($package, $this->vendorDir, $url);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package) {
        parent::uninstall($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType) {
        return 'non-destructive-archive-installer' === $packageType;
    }

    /**
     * Returns the URL of the last file that this install process ever downloaded.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getLastDownloadedFileUrl(PackageInterface $package, $vendorDir) {
        $packageDir = self::getPackageDir($package, $vendorDir);
        if (file_exists($packageDir."download-status.txt")) {
            return file_get_contents($packageDir."download-status.txt");
        } else {
            return null;
        }
    }

    /**
     * Saves the URL of the last file that this install process downloaded into a file for later retrieval.
     *
     * @param PackageInterface $package
     * @param unknown $url
     */
    public static function setLastDownloadedFileUrl(PackageInterface $package, $vendorDir, $url) {
        $packageDir = self::getPackageDir($package, $vendorDir);
        if (!file_exists($packageDir)) mkdir($packageDir, 0777, true);
        file_put_contents($packageDir."download-status.txt", $url);
    }

    /**
     * Returns the package directory, with a trailing /
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getPackageDir(PackageInterface $package, $vendorDir) {
        return realpath($vendorDir) . '/' . $package->getName() . '/';
    }

    /**
     * Extract ZIP (copied from Composer's ZipDownloader)
     *
     * @param string $file
     * @param string $path
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    protected function extractZip($file, $path, $omitFirstDirectory) {

        if (!class_exists('ZipArchive'))
            throw new \RuntimeException('You need the zip extension enabled to use the ZipDownloader');

        $zipArchive = new \ZipArchive();

        if (true !== $zipArchive->open($file))
            throw new \UnexpectedValueException("Unable to open downloaded ZIP file.");

        if ($omitFirstDirectory) {
            $this->extractZipIgnoringFirstDirectory($zipArchive, $path);
        } else {
            if (true !== $zipArchive->extractTo($path))
                throw new \RuntimeException("There was an error extracting the ZIP file. Corrupt file?");
        }

        $zipArchive->close();
    }

    /**
     * Extract the ZIP file, but ignores the first directory of the ZIP file.
     * This is useful if you want to extract a ZIP file that contains all the content stored
     * in one directory and that you don't want this directory.
     *
     * @param unknown $zipArchive
     * @param unknown $path
     * @throws \Exception
     */
    protected function extractZipIgnoringFirstDirectory($zipArchive, $path) {

        for( $i = 0; $i < $zipArchive->numFiles; $i++ ){

            // vars
            $stat       = $zipArchive->statIndex($i);
            $filename   = $stat['name'];
            $pos        = strpos($filename, '/');

            // The file name, without the the directory
            $newfilename = $pos !== false
                ? substr($filename, $pos+1)
                : $filename;

            // Skip to next file if this file is null
            if (!$newfilename) continue;

            // Can we read from the Zip?
            if (!$fp = $zipArchive->getStream($filename))
                throw new \Exception("Unable to read file $filename from archive.");

            // make directory if needed
            if (!file_exists(dirname($path . $newfilename))) {
                if ($this->debug) $this->io->write("    Creating Directory <info>" . dirname($path . $newfilename). "</info>");
                mkdir(dirname($path . $newfilename), 0777, true);
            }

            // If the current file is actually a directory, let's pass.
            if (strrpos($newfilename, '/') == strlen($newfilename)-1) continue;

            // write the file
            if ($this->debug) $this->io->write("    Extracting File <info>" . $path . $newfilename . "</info>");
            $fpWrite = fopen($path . $newfilename, "wb");
            while (!feof($fp)) {
                fwrite($fpWrite, fread($fp, 65536));
            }
        }
    }

    /**
     * Extract tar, tar.gz or tar.bz2 (copied from Composer's TarDownloader)
     *
     * @param string $file
     * @param string $path
     */
    protected function extractTgz($file, $path, $omitFirstDirectory) {

        $archive = new \PharData($file);

        if ($omitFirstDirectory) {
            $this->extractTgzIgnoringFirstDirectory($archive, $path);
        } else {
            $archive->extractTo($path, null, true);
        }
    }

    /**
     * Extract the ZIP file, but ignores the first directory of the ZIP file.
     * This is useful if you want to extract a ZIP file that contains all the content stored
     * in one directory and that you don't want this directory.
     *
     * @param unknown $zipArchive
     * @param unknown $path
     * @throws \Exception
     */
    protected function extractTgzIgnoringFirstDirectory($tgzArchive, $path) {

        // tmp path
        $tmp_path = sys_get_temp_dir() . '/non-destructive-archive-installer/' . uniqid();

        // extract to the tmp path
        $tgzArchive->extractTo($tmp_path);

        // get all the files
        $files = $this->directoryToArray($tmp_path, true);

        foreach ($files as $file){

            // vars
            $filename = str_replace($tmp_path . '/', '', $file);
            $pos      = strpos($filename, '/');

            // The file name, without the the directory
            $newfilename = $pos !== false
                ? substr($filename, $pos+1)
                : $filename;

            // Skip to next file if this file is null
            if (!$newfilename) continue;

            // make directory if needed
            if (!file_exists(dirname($path . $newfilename))) {
                if ($this->debug) $this->io->write("    Creating Directory <info>" . dirname($path . $newfilename). "</info>");
                mkdir(dirname($path . $newfilename), 0777, true);
            }

            // If the current file is actually a directory, let's pass.
            if (is_dir($file)) continue;

            // write the file
            if ($this->debug) $this->io->write("    Extracting File <info>" . $path . $newfilename . "</info>");
            rename($file, $path . $newfilename);
        }
    }

    protected function directoryToArray($directory, $recursive) {
        $array_items = array();
        if ($handle = opendir($directory)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if (is_dir($directory. "/" . $file)) {
                        if($recursive) {
                            $array_items = array_merge($array_items, $this->directoryToArray($directory. "/" . $file, $recursive));
                        }
                        $file = $directory . "/" . $file;
                        $array_items[] = preg_replace("/\/\//si", "/", $file);
                    } else {
                        $file = $directory . "/" . $file;
                        $array_items[] = preg_replace("/\/\//si", "/", $file);
                    }
                }
            }
            closedir($handle);
        }
        return $array_items;
    }

}