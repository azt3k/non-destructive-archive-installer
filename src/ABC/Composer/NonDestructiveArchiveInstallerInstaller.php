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
 *
 * @author David Négrier
 */
class NonDestructiveArchiveInstallerInstaller extends LibraryInstaller {

    protected $rfs;

    /**
     * Initializes library installer.
     *
     * @param IOInterface $io
     * @param Composer    $composer
     * @param string      $type
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);
        $this->rfs = new RemoteFilesystem($io);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $package)
    {
        parent::update($repo, $initial, $package);

        $this->downloadAndExtractFile($package);
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->downloadAndExtractFile($package);
    }

    /**
     * Downloads and extracts the package, only if the URL to download has not been downloaded before.
     *
     * @param PackageInterface $package
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    private function downloadAndExtractFile(PackageInterface $package) {

        if (!$extra = $this->composer->getPackage()->getExtra()) 
            $extra = $package->getExtra();

        die(
            'composer package extra' . print_r($this->composer->getPackage()->getExtra(), 1) .
            'passed package extra' . print_r($package->getExtra(), 1)
        );

        $url = $package->getDistUrl();

        if ($url) {

            // handle package level config
            // ---------------------------
             
            $omitFirstDirectory = (isset($extra['omit-first-directory']))
                ? strtolower($extra['omit-first-directory']) == "true"
                : false;

            $targetDir = isset($extra['target-dir'])
                ? $extra['target-dir']
                : $this->getInstallPath($package);

            // handle overrides
            // ---------------------------



            // First, try to detect if the archive has been downloaded
            // If yes, do nothing.
            // If no, let's download the package.
            if (self::getLastDownloadedFileUrl($package) == $url) {
                return;
            }

            // Download (using code from FileDownloader)
            $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

            if (!extension_loaded('openssl') && 0 === strpos($url, 'https:')) {
                throw new \RuntimeException('You must enable the openssl extension to download files via https');
            }


            $this->io->write("    - Downloading <info>" . $fileName . "</info> from <info>".$url."</info>");

            $this->rfs->copy(parse_url($url, PHP_URL_HOST), $url, $fileName);

            if (!file_exists($fileName)) {
                throw new \UnexpectedValueException($url.' could not be saved to '.$fileName.', make sure the'
                .' directory is writable and you have internet connectivity');
            }

            // Extract using ZIP downloader
            if ($extension == 'zip') {
                $this->io->write("    - Extracting <info>" . $fileName . "</info> to <info>" . $targetDir . "</info>");
                $this->extractZip($fileName, $targetDir, $omitFirstDirectory);
            } elseif ($extension == 'tar' || $extension == 'gz' || $extension == 'bz2') {
                $this->io->write("    - Extracting <info>" . $fileName . "</info> to <info>" . $targetDir . "</info>");
                $this->extractTgz($fileName, $targetDir, $omitFirstDirectory);
            }

            // Delete archive once download is performed
            unlink($fileName);

            // Save last download URL
            self::setLastDownloadedFileUrl($package, $url);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'non-destructive-archive-installer' === $packageType;
    }

    /**
     * Returns the URL of the last file that this install process ever downloaded.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getLastDownloadedFileUrl(PackageInterface $package) {
        $packageDir = self::getPackageDir($package);
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
    public static function setLastDownloadedFileUrl(PackageInterface $package, $url) {
        $packageDir = self::getPackageDir($package);
        file_put_contents($packageDir."download-status.txt", $url);
    }

    /**
     * Returns the package directory, with a trailing /
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getPackageDir(PackageInterface $package) {
        return __DIR__."/../../../../../".$package->getName()."/";
    }

    /**
     * Extract ZIP (copied from Composer's ZipDownloader)
     *
     * @param string $file
     * @param string $path
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    protected function extractZip($file, $path, $omitFirstDirectory)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('You need the zip extension enabled to use the ZipDownloader');
        }

        $zipArchive = new \ZipArchive();

        if (true !== ($retval = $zipArchive->open($file))) {
            throw new \UnexpectedValueException("Unable to open downloaded ZIP file.");
        }

        if ($omitFirstDirectory) {
            $this->extractIgnoringFirstDirectory($zipArchive, $path);
        } else {
            if (true !== $zipArchive->extractTo($path)) {
                throw new \RuntimeException("There was an error extracting the ZIP file. Corrupt file?");
            }
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
    protected function extractIgnoringFirstDirectory($zipArchive, $path) {
        for( $i = 0; $i < $zipArchive->numFiles; $i++ ){
            $stat = $zipArchive->statIndex( $i );

            $filename = $stat['name'];

            $pos = strpos($filename, '/');
            if ($pos !== false) {
                // The file name, without the the directory
                $newfilename = substr($filename, $pos+1);
            } else {
                $newfilename = $filename;
            }

            if (!$newfilename) {
                continue;
            }

            $fp = $zipArchive->getStream($filename);
            if  (!$fp) {
                throw new \Exception("Unable to read file $filename from archive.");
            }

            if (!file_exists(dirname($path.$newfilename))) {
                mkdir(dirname($path.$newfilename), 0777, true);
            }

            // If the current file is actually a directory, let's pass.
            if (strrpos($newfilename, '/') == strlen($newfilename)-1) {
                continue;
            }

            $fpWrite = fopen($path.$newfilename, "wb");

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
    protected function extractTgz($file, $path, $omitFirstDirectory)
    {
        if ($omitFirstDirectory) {
            throw new \Exception("Sorry! The omit-first-directory parameter is currently only supported for ZIP files.");
        }
        // Can throw an UnexpectedValueException
        $archive = new \PharData($file);
        $archive->extractTo($path, null, true);
    }
}