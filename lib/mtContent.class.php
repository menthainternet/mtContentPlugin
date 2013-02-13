<?php

/**
 * Send content to client with custom filename and / or MIME type.
 * Move uploaded file to destination directory.
 *
 * @package mtContentPlugin
 * @author  szinya <szinya@mentha.hu>
 */
class mtContent
{
  const NS_SET_FILENAME = 'mentha/content/set_filename';

 /**
  * Set filename sent to client.
  * Saves request method and params then redirects to mtContent/setFilename
  * action with filename appended to URL.
  *
  * @static
  *
  * @param string $filename    Filename
  * @param array  $routeParams Custom routing parameters
  *
  * @throws InvalidArgumentException If filename is empty
  * @throws sfStopException          On redirect
  */
  static public function setFilename($filename, $routeParams = array())
  {
    $context = sfContext::getInstance();
    $userAttributes = $context->getUser()->getAttributeHolder();

    // return to caller action if filename already set
    if (true === $userAttributes->get('redirected', null, self::NS_SET_FILENAME))
    {
      $userAttributes->add(array(
        'redirected' => null,
        'request_method' => null,
        'request_params' => null
      ), self::NS_SET_FILENAME);

      return;
    }

    $context->getConfiguration()->loadHelpers(array('mtFilename', 'Url'));

    $filename = mt_clean_filename(trim($filename));

    if (empty($filename))
    {
      throw new InvalidArgumentException('Can not set empty filename.');
    }

    $request = $context->getRequest();

    // initialize redirect, store request method and params
    $userAttributes->add(array(
      'redirected' => false,
      'request_method' => $request->getMethod(),
      'request_params' => $request->getParameterHolder()->getAll()
    ), self::NS_SET_FILENAME);

    if (!sfConfig::get('app_mt_content_plugin_set_filename_route_pattern'))
    {
      $routeParams = array(
        'mt_content_module' => $request->getParameter('module'),
        'mt_content_action' => $request->getParameter('action')
      );
    }

    $context->getController()->redirect(url_for('mt_content_set_filename', $routeParams) . "/" . rawurlencode($filename));

    throw new sfStopException();
  }

 /**
  * Sends content to client.
  *
  * @static
  *
  * @param  mixed  $content Content to send
  * @param  array  $options Available options: type, charset, extension, force_download
  *
  * @throws sfStopException On sending content
  */
  static public function send($content, $options = array())
  {
    $context = sfContext::getInstance();
    $request = $context->getRequest();
    $response = $context->getResponse();

    if (!is_array($options))
    {
      $options = array();
    }

    $options['type'] = isset($options['type']) ? strtolower(trim($options['type'])) : null;
    $options['charset'] = isset($options['charset']) ? strtolower(trim($options['charset'])) : 'utf-8';
    $options['extension'] = isset($options['extension']) ? strtolower(trim($options['extension'])) : null;
    $options['force_download'] = isset($options['force_download']) ? (bool)$options['force_download'] : null;

    if (!$options['type'])
    {
      // get extension from request URI
      if (!$options['extension'])
      {
        $context->getConfiguration()->loadHelpers(array('mtFilename'));

        $filename = mt_parse_filename(basename(rawurldecode(parse_url($request->getUri(), PHP_URL_PATH))));
        $options['extension'] = $filename['extension'];
      }

      $options['type'] = self::getTypeFromExtension($options['extension']);
    }

    // set charset for text content and json, force download for others
    if ((false === stripos($options['type'], 'charset') && (0 === stripos($options['type'], 'text/') || strlen($options['type']) - 3 === strripos($options['type'], 'xml'))) || 'application/json' == $options['type'])
    {
      if ($options['charset'])
      {
        $options['type'] .= '; charset=' . $options['charset'];
      }
    }
    elseif (null === $options['force_download'])
    {
      $options['force_download'] = true;
    }

    $response->setContentType($options['type']);

    if ($options['force_download'])
    {
      $response->setHttpHeader('content-disposition', 'attachment');
    }

    // treat array as json data
    if (is_array($content))
    {
      $content = json_encode($content);
    }

    $response->setHttpHeader('content-length', strlen($content));

    $response->setContent($content);
    $response->send();

    throw new sfStopException();
  }

 /**
  * Moves uploaded file to specified location.
  *
  * @static
  *
  * @param  array  $file        A value of $_FILES
  * @param  string $destination Directory to move file
  * @param  bool   $overwrite   Whether to overwrite existing file
  *
  * @throws InvalidArgumentException If file is not a valid uploaded file
  * @throws InvalidArgumentException If destination is not an existing directory
  * @throws RuntimeException         If moving uploaded file fails
  *
  * @return string Filename on success
  */
  static public function moveUploadedFile($file, $destination, $overwrite = false)
  {
    if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
    {
      throw new InvalidArgumentException('File must be a valid uploaded file.');
    }

    if (!is_dir($destination))
    {
      throw new InvalidArgumentException(sprintf('Destination "%s" must be an existing directory.', $destination));
    }

    sfContext::getInstance()->getConfiguration()->loadHelpers(array('mtFilename'));

    $filename = mt_clean_filename($file['name']);

    if (true !== $overwrite)
    {
      $filename = mt_unique_filename($filename, $destination);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination . '/' . $filename))
    {
      throw new RuntimeException(sprintf('Error moving uploaded file "%s" to "%s".', $filename, $destination));
    }

    return $filename;
  }

 /**
  * Returns the content type associated with the given extension.
  *
  * @static
  *
  * @param  string $extension The extension
  * @param  string $default   The default content type to use
  *
  * @return string The content type
  */
  static protected function getTypeFromExtension($extension, $default = '')
  {
    // source: https://rubygems.org/gems/mime-types v1.21
    // excluding unregistered, obsolete and platform-specific types
    static $types = array(
      '123' => 'application/vnd.lotus-1-2-3',
      '3g2' => 'video/3gpp2',
      '3gp' => 'video/3gpp',
      '3gpp' => 'video/3gpp',
      '3gpp2' => 'video/3gpp2',
      'aac' => 'audio/x-aac',
      'acutc' => 'application/vnd.acucorp',
      'ai' => 'application/postscript',
      'aif' => 'audio/x-aiff',
      'aifc' => 'audio/x-aiff',
      'aiff' => 'audio/x-aiff',
      'ami' => 'application/vnd.amiga.ami',
      'amr' => 'audio/amr',
      'ani' => 'application/octet-stream',
      'appcache' => 'text/cache-manifest',
      'asc' => 'text/plain',
      'asf' => 'video/x-ms-asf',
      'asx' => 'video/x-ms-asf',
      'atc' => 'application/vnd.acucorp',
      'atom' => 'application/atom+xml',
      'au' => 'audio/basic',
      'avi' => 'video/x-msvideo',
      'awb' => 'audio/amr-wb',
      'bat' => 'application/x-msdos-program',
      'bck' => 'application/x-vmsbackup',
      'bcpio' => 'application/x-bcpio',
      'bin' => 'application/octet-stream',
      'bkm' => 'application/vnd.nervana',
      'bleep' => 'application/x-bleeper',
      'bmp' => 'image/x-bmp',
      'book' => 'application/x-maker',
      'bpd' => 'application/vnd.hbci',
      'bz2' => 'application/x-bzip2',
      'c' => 'text/plain',
      'cab' => 'application/vnd.ms-cab-compressed',
      'cc' => 'text/plain',
      'ccc' => 'text/vnd.net2phone.commcenter.command',
      'cdf' => 'application/x-netcdf',
      'cdy' => 'application/vnd.cinderella',
      'cer' => 'application/pkix-cert',
      'chrt' => 'application/vnd.kde.kchart',
      'cil' => 'application/vnd.ms-artgalry',
      'class' => 'application/x-java-vm',
      'cmc' => 'application/vnd.cosmocaller',
      'cmd' => 'application/x-msdos-program',
      'coffee' => 'text/x-coffescript',
      'com' => 'application/x-msdownload',
      'cpio' => 'application/x-cpio',
      'cpp' => 'text/plain',
      'cpt' => 'application/x-mac-compactpro',
      'crl' => 'application/pkix-crl',
      'crt' => 'application/x-x509-ca-cert',
      'crx' => 'application/x-chrome-extension',
      'csh' => 'application/x-csh',
      'csm' => 'application/x-cu-seeme',
      'css' => 'text/css',
      'csv' => 'text/csv',
      'cu' => 'application/x-cu-seeme',
      'curl' => 'application/vnd.curl',
      'cw' => 'application/prs.cww',
      'cww' => 'application/prs.cww',
      'dat' => 'text/plain',
      'dcm' => 'application/dicom',
      'dcr' => 'application/x-director',
      'deb' => 'application/x-debian-package',
      'dfac' => 'application/vnd.dreamfactory',
      'dgn' => 'image/x-vnd.dgn',
      'dir' => 'application/x-director',
      'djv' => 'image/vnd.djvu',
      'djvu' => 'image/vnd.djvu',
      'dl' => 'video/x-dl',
      'dll' => 'application/octet-stream',
      'dms' => 'application/octet-stream',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'dot' => 'application/msword',
      'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
      'dtd' => 'text/xml',
      'dv' => 'video/x-dv',
      'dvi' => 'application/x-dvi',
      'dwf' => 'x-drawing/dwf',
      'dwg' => 'image/vnd.dwg',
      'dxr' => 'application/x-director',
      'dylib' => 'application/octet-stream',
      'ecelp4800' => 'audio/vnd.nuera.ecelp4800',
      'ecelp7470' => 'audio/vnd.nuera.ecelp7470',
      'ecelp9600' => 'audio/vnd.nuera.ecelp9600',
      'efif' => 'application/vnd.picsel',
      'eml' => 'message/rfc822',
      'emm' => 'application/vnd.ibm.electronic-media',
      'ent' => 'application/vnd.nervana',
      'entity' => 'application/vnd.nervana',
      'eol' => 'audio/vnd.digital-winds',
      'eps' => 'application/postscript',
      'epub' => 'application/epub+zip',
      'etx' => 'text/x-setext',
      'evc' => 'audio/evrc',
      'exe' => 'application/x-msdownload',
      'f4a' => 'audio/mp4',
      'f4b' => 'audio/mp4',
      'f4p' => 'video/mp4',
      'f4v' => 'video/mp4',
      'fb' => 'application/x-maker',
      'fbdoc' => 'application/x-maker',
      'fli' => 'video/x-fli',
      'flo' => 'application/vnd.micrografx.flo',
      'flv' => 'video/x-flv',
      'flw' => 'application/vnd.kde.kivio',
      'fm' => 'application/x-maker',
      'frame' => 'application/x-maker',
      'frm' => 'application/x-maker',
      'fsc' => 'application/vnd.fsc.weblaunch',
      'gif' => 'image/gif',
      'gl' => 'video/x-gl',
      'gtar' => 'application/x-gtar',
      'gz' => 'application/x-gzip',
      'h' => 'text/plain',
      'hbc' => 'application/vnd.hbci',
      'hbci' => 'application/vnd.hbci',
      'hdf' => 'application/x-hdf',
      'hep' => 'application/x-hep',
      'hh' => 'text/plain',
      'hlp' => 'text/plain',
      'hpgl' => 'application/vnd.hp-hpgl',
      'hpp' => 'text/plain',
      'hqx' => 'application/mac-binhex40',
      'htc' => 'text/x-component',
      'htke' => 'application/vnd.kenameaapp',
      'htm' => 'text/html',
      'html' => 'text/html',
      'htmlx' => 'text/html',
      'htx' => 'text/html',
      'hvd' => 'application/vnd.yamaha.hv-dic',
      'hvp' => 'application/vnd.yamaha.hv-voice',
      'hvs' => 'application/vnd.yamaha.hv-script',
      'ibooks' => 'application/x-ibooks+zip',
      'ica' => 'application/x-ica',
      'ice' => 'x-conference/x-cooltalk',
      'ico' => 'image/vnd.microsoft.icon',
      'ief' => 'image/ief',
      'iges' => 'model/iges',
      'igs' => 'model/iges',
      'igx' => 'application/vnd.micrografx.igx',
      'imagemap' => 'application/x-imagemap',
      'imap' => 'application/x-imagemap',
      'irm' => 'application/vnd.ibm.rights-management',
      'irp' => 'application/vnd.irepository.package+xml',
      'ivf' => 'video/x-ivf',
      'jad' => 'text/vnd.sun.j2me.app-descriptor',
      'jar' => 'application/x-java-archive',
      'jisp' => 'application/vnd.jisp',
      'jnlp' => 'application/x-java-jnlp-file',
      'jp2' => 'image/jp2',
      'jpe' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'jpf' => 'image/jpx',
      'jpg' => 'image/jpeg',
      'jpg2' => 'image/jp2',
      'jpgm' => 'image/jpm',
      'jpm' => 'image/jpm',
      'jpx' => 'image/jpx',
      'js' => 'application/javascript',
      'json' => 'application/json',
      'kar' => 'audio/x-midi',
      'karbon' => 'application/vnd.kde.karbon',
      'kcm' => 'application/vnd.nervana',
      'key' => 'application/x-iwork-keynote-sffkey',
      'kfo' => 'application/vnd.kde.kformula',
      'kia' => 'application/vnd.kidspiration',
      'kml' => 'application/vnd.google-earth.kml+xml',
      'kmz' => 'application/vnd.google-earth.kmz',
      'kne' => 'application/vnd.kinar',
      'knp' => 'application/vnd.kinar',
      'kom' => 'application/vnd.hbci',
      'kon' => 'application/vnd.kde.kontour',
      'kpr' => 'application/vnd.kde.kpresenter',
      'kpt' => 'application/vnd.kde.kpresenter',
      'ksp' => 'application/vnd.kde.kspread',
      'kwd' => 'application/vnd.kde.kword',
      'kwt' => 'application/vnd.kde.kword',
      'l16' => 'audio/l16',
      'latex' => 'application/x-latex',
      'lbd' => 'application/vnd.llamagraphics.life-balance.desktop',
      'lbe' => 'application/vnd.llamagraphics.life-balance.exchange+xml',
      'les' => 'application/vnd.hhe.lesson-player',
      'lha' => 'application/octet-stream',
      'lrm' => 'application/vnd.ms-lrm',
      'ltx' => 'application/x-latex',
      'lvp' => 'audio/vnd.lucent.voice',
      'lzh' => 'application/octet-stream',
      'm4a' => 'audio/mp4a-latm',
      'm4u' => 'video/vnd.mpegurl',
      'm4v' => 'video/vnd.objectvideo',
      'maker' => 'application/x-maker',
      'man' => 'application/x-troff-man',
      'manifest' => 'text/cache-manifest',
      'mcd' => 'application/vnd.mcd',
      'mda' => 'application/x-msaccess',
      'mdb' => 'application/x-msaccess',
      'mde' => 'application/x-msaccess',
      'mdf' => 'application/x-msaccess',
      'mdi' => 'image/vnd.ms-modi',
      'me' => 'application/x-troff-me',
      'mesh' => 'model/mesh',
      'mfm' => 'application/vnd.mfmp',
      'mid' => 'audio/x-midi',
      'midi' => 'audio/x-midi',
      'mif' => 'application/x-mif',
      'mj2' => 'video/mj2',
      'mjp2' => 'video/mj2',
      'mjpg' => 'video/x-motion-jpeg',
      'mkv' => 'video/x-matroska',
      'mmf' => 'application/vnd.smaf',
      'mobi' => 'application/x-mobipocket-ebook',
      'mov' => 'video/quicktime',
      'movie' => 'video/x-sgi-movie',
      'mp2' => 'video/mpeg',
      'mp3' => 'audio/mpeg',
      'mp3g' => 'video/mpeg',
      'mp4' => 'video/vnd.objectvideo',
      'mpc' => 'application/vnd.mophun.certificate',
      'mpe' => 'video/mpeg',
      'mpeg' => 'video/mpeg',
      'mpg' => 'video/mpeg',
      'mpg4' => 'video/mp4',
      'mpga' => 'audio/mpeg',
      'mpm' => 'application/vnd.blueice.multipass',
      'mpn' => 'application/vnd.mophun.application',
      'mpp' => 'application/vnd.ms-project',
      'ms' => 'application/x-troff-ms',
      'mseq' => 'application/vnd.mseq',
      'msh' => 'model/mesh',
      'mxf' => 'application/mxf',
      'mxmf' => 'audio/vnd.nokia.mobile-xmf',
      'mxu' => 'video/vnd.mpegurl',
      'nc' => 'application/x-netcdf',
      'nim' => 'video/vnd.nokia.interleaved-multimedia',
      'numbers' => 'application/x-iwork-numbers-sffnumbers',
      'oda' => 'application/oda',
      'odb' => 'application/vnd.oasis.opendocument.database',
      'odc' => 'application/vnd.oasis.opendocument.chart-template',
      'odf' => 'application/vnd.oasis.opendocument.formula-template',
      'odg' => 'application/vnd.oasis.opendocument.graphics',
      'odi' => 'application/vnd.oasis.opendocument.image-template',
      'odm' => 'application/vnd.oasis.opendocument.text-master',
      'odp' => 'application/vnd.oasis.opendocument.presentation',
      'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
      'odt' => 'application/vnd.oasis.opendocument.text',
      'oex' => 'application/x-opera-extension',
      'ogg' => 'video/ogg',
      'ogv' => 'video/ogg',
      'ogx' => 'application/ogg',
      'oprc' => 'application/vnd.palm',
      'otf' => 'application/x-font-opentype',
      'otg' => 'application/vnd.oasis.opendocument.graphics-template',
      'oth' => 'application/vnd.oasis.opendocument.text-web',
      'otp' => 'application/vnd.oasis.opendocument.presentation-template',
      'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
      'ott' => 'application/vnd.oasis.opendocument.text-template',
      'p10' => 'application/pkcs10',
      'p7c' => 'application/pkcs7-mime',
      'p7m' => 'application/pkcs7-mime',
      'p7s' => 'application/pkcs7-signature',
      'pac' => 'application/x-ns-proxy-autoconfig',
      'pages' => 'application/x-iwork-pages-sffpages',
      'pbm' => 'image/x-portable-bitmap',
      'pdb' => 'x-chemical/x-pdb',
      'pdf' => 'application/pdf',
      'pfr' => 'application/font-tdpfr',
      'pgb' => 'image/vnd.globalgraphics.pgb',
      'pgm' => 'image/x-portable-graymap',
      'pgn' => 'application/x-chess-pgn',
      'pgp' => 'application/octet-stream',
      'php' => 'application/x-httpd-php',
      'pht' => 'application/x-httpd-php',
      'phtml' => 'application/x-httpd-php',
      'pkd' => 'application/vnd.hbci',
      'pki' => 'application/pkixcmp',
      'pkipath' => 'application/pkix-pkipath',
      'pl' => 'application/x-perl',
      'plb' => 'application/vnd.3gpp.pic-bw-large',
      'plj' => 'audio/vnd.everad.plj',
      'plt' => 'application/vnd.hp-hpgl',
      'pm' => 'application/x-perl',
      'pm5' => 'application/x-pagemaker',
      'png' => 'image/png',
      'pnm' => 'image/x-portable-anymap',
      'pot' => 'application/vnd.ms-powerpoint',
      'potm' => 'application/vnd.ms-powerpoint.template.macroenabled.12',
      'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
      'ppm' => 'image/x-portable-pixmap',
      'pps' => 'application/vnd.ms-powerpoint',
      'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
      'ppt' => 'application/vnd.ms-powerpoint',
      'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'pqa' => 'application/vnd.palm',
      'prc' => 'application/vnd.palm',
      'ps' => 'application/postscript',
      'psb' => 'application/vnd.3gpp.pic-bw-small',
      'psp' => 'image/x-paintshoppro',
      'pspimage' => 'image/x-paintshoppro',
      'pt5' => 'application/x-pagemaker',
      'pti' => 'application/vnd.pvi.ptid1',
      'ptid' => 'application/vnd.pvi.ptid1',
      'pvb' => 'application/vnd.3gpp.pic-bw-var',
      'py' => 'application/x-python',
      'qt' => 'video/quicktime',
      'qtl' => 'application/x-quicktimeplayer',
      'qwd' => 'application/vnd.quark.quarkxpress',
      'qwt' => 'application/vnd.quark.quarkxpress',
      'qxb' => 'application/vnd.quark.quarkxpress',
      'qxd' => 'application/vnd.quark.quarkxpress',
      'qxl' => 'application/vnd.quark.quarkxpress',
      'qxt' => 'application/vnd.quark.quarkxpress',
      'ra' => 'audio/x-realaudio',
      'ram' => 'audio/x-pn-realaudio',
      'rar' => 'application/x-rar-compressed',
      'ras' => 'image/x-cmu-raster',
      'rb' => 'application/x-ruby',
      'rbw' => 'application/x-ruby',
      'rcprofile' => 'application/vnd.ipunplugged.rcprofile',
      'rct' => 'application/prs.nprend',
      'rdf' => 'application/rdf+xml',
      'rdz' => 'application/vnd.data-vision.rdz',
      'req' => 'application/vnd.nervana',
      'request' => 'application/vnd.nervana',
      'rgb' => 'image/x-rgb',
      'rhtml' => 'application/x-html+ruby',
      'rnd' => 'application/prs.nprend',
      'roff' => 'text/troff',
      'rpm' => 'audio/x-pn-realaudio-plugin',
      'rpss' => 'application/vnd.nokia.radio-presets',
      'rpst' => 'application/vnd.nokia.radio-preset',
      'rss' => 'application/rss+xml',
      'rst' => 'text/prs.fallenstein.rst',
      'rtf' => 'text/rtf',
      'rtx' => 'text/richtext',
      's11' => 'video/vnd.sealed.mpeg1',
      's14' => 'video/vnd.sealed.mpeg4',
      's1a' => 'application/vnd.sealedmedia.softseal.pdf',
      's1e' => 'application/vnd.sealed.xls',
      's1h' => 'application/vnd.sealedmedia.softseal.html',
      's1m' => 'audio/vnd.sealedmedia.softseal.mpeg',
      's1p' => 'application/vnd.sealed.ppt',
      's1q' => 'video/vnd.sealedmedia.softseal.mov',
      's1w' => 'application/vnd.sealed.doc',
      'saf' => 'application/vnd.yamaha.smaf-audio',
      'sav' => 'application/x-spss',
      'sbs' => 'application/x-spss',
      'sc' => 'application/vnd.ibm.secure-container',
      'sdf' => 'application/vnd.kinar',
      'sdo' => 'application/vnd.sealed.doc',
      'sdoc' => 'application/vnd.sealed.doc',
      'see' => 'application/vnd.seemail',
      'sem' => 'application/vnd.sealed.eml',
      'seml' => 'application/vnd.sealed.eml',
      'ser' => 'application/x-java-serialized-object',
      'sgm' => 'text/sgml',
      'sgml' => 'text/sgml',
      'sh' => 'application/x-sh',
      'shar' => 'application/x-shar',
      'shtml' => 'text/html',
      'si' => 'text/vnd.wap.si',
      'sic' => 'application/vnd.wap.sic',
      'sig' => 'application/pgp-signature',
      'silo' => 'model/mesh',
      'sit' => 'application/x-stuffit',
      'siv' => 'application/sieve',
      'skd' => 'application/x-koan',
      'skm' => 'application/x-koan',
      'skp' => 'application/x-koan',
      'skt' => 'application/x-koan',
      'sl' => 'text/vnd.wap.sl',
      'slc' => 'application/vnd.wap.slc',
      'smh' => 'application/vnd.sealed.mht',
      'smht' => 'application/vnd.sealed.mht',
      'smi' => 'application/smil+xml',
      'smil' => 'application/smil+xml',
      'smo' => 'video/vnd.sealedmedia.softseal.mov',
      'smov' => 'video/vnd.sealedmedia.softseal.mov',
      'smp' => 'audio/vnd.sealedmedia.softseal.mpeg',
      'smp3' => 'audio/vnd.sealedmedia.softseal.mpeg',
      'smpg' => 'video/vnd.sealed.mpeg4',
      'sms' => 'application/vnd.3gpp.sms',
      'smv' => 'audio/smv',
      'snd' => 'audio/basic',
      'so' => 'application/octet-stream',
      'soc' => 'application/sgml-open-catalog',
      'spd' => 'application/vnd.sealedmedia.softseal.pdf',
      'spdf' => 'application/vnd.sealedmedia.softseal.pdf',
      'spf' => 'application/vnd.yamaha.smaf-phrase',
      'spl' => 'application/x-futuresplash',
      'spo' => 'application/x-spss',
      'spp' => 'application/x-spss',
      'sppt' => 'application/vnd.sealed.ppt',
      'sps' => 'application/x-spss',
      'src' => 'application/x-wais-source',
      'ssw' => 'video/vnd.sealed.swf',
      'sswf' => 'video/vnd.sealed.swf',
      'stk' => 'application/hyperstudio',
      'stm' => 'application/vnd.sealedmedia.softseal.html',
      'stml' => 'application/vnd.sealedmedia.softseal.html',
      'sus' => 'application/vnd.sus-calendar',
      'susp' => 'application/vnd.sus-calendar',
      'sv4cpio' => 'application/x-sv4cpio',
      'sv4crc' => 'application/x-sv4crc',
      'svg' => 'image/svg+xml',
      'svgz' => 'image/svg+xml',
      'swf' => 'application/x-shockwave-flash',
      'sxl' => 'application/vnd.sealed.xls',
      'sxls' => 'application/vnd.sealed.xls',
      't' => 'text/troff',
      'tar' => 'application/x-tar',
      'tbk' => 'application/x-toolbook',
      'tbz' => 'application/x-gtar',
      'tbz2' => 'application/x-gtar',
      'tcl' => 'application/x-tcl',
      'tex' => 'application/x-tex',
      'texi' => 'application/x-texinfo',
      'texinfo' => 'application/x-texinfo',
      'tga' => 'image/x-targa',
      'tgz' => 'application/x-gtar',
      'tif' => 'image/tiff',
      'tiff' => 'image/tiff',
      'tr' => 'text/troff',
      'troff' => 'text/troff',
      'ts' => 'video/mp2t',
      'tsv' => 'text/tab-separated-values',
      'ttf' => 'application/x-font-truetype',
      'txd' => 'application/vnd.genomatix.tuxedo',
      'txt' => 'text/plain',
      'upa' => 'application/vnd.hbci',
      'ustar' => 'application/x-ustar',
      'vbk' => 'audio/vnd.nortel.vbk',
      'vcd' => 'application/x-cdlink',
      'vcf' => 'text/x-vcard',
      'vcs' => 'text/x-vcalendar',
      'vis' => 'application/vnd.visionary',
      'viv' => 'video/vnd.vivo',
      'vivo' => 'video/vnd.vivo',
      'vrml' => 'x-world/x-vrml',
      'vsc' => 'application/vnd.vidsoft.vidconference',
      'vsd' => 'application/vnd.visio',
      'vss' => 'application/vnd.visio',
      'vst' => 'application/vnd.visio',
      'vsw' => 'application/vnd.visio',
      'wav' => 'audio/x-wav',
      'wax' => 'audio/x-ms-wax',
      'wbs' => 'application/vnd.criticaltools.wbs+xml',
      'wbxml' => 'application/vnd.wap.wbxml',
      'webapp' => 'application/x-web-app-manifest+json',
      'webp' => 'image/webp',
      'wif' => 'application/watcherinfo+xml',
      'wks' => 'application/vnd.lotus-1-2-3',
      'wm' => 'video/x-ms-wm',
      'wma' => 'audio/x-ms-wma',
      'wmd' => 'application/x-ms-wmd',
      'wml' => 'text/vnd.wap.wml',
      'wmlc' => 'application/vnd.wap.wmlc',
      'wmls' => 'text/vnd.wap.wmlscript',
      'wmlsc' => 'application/vnd.wap.wmlscriptc',
      'wmv' => 'video/x-ms-wmv',
      'wmx' => 'video/x-ms-wmx',
      'wmz' => 'application/x-ms-wmz',
      'woff' => 'application/font-woff',
      'wp' => 'application/wordperfect5.1',
      'wp5' => 'application/wordperfect5.1',
      'wp6' => 'application/x-wordperfect6.1',
      'wpd' => 'application/vnd.wordperfect',
      'wpl' => 'application/vnd.ms-wpl',
      'wqd' => 'application/vnd.wqd',
      'wrd' => 'application/msword',
      'wrl' => 'x-world/x-vrml',
      'wtb' => 'application/vnd.webturbo',
      'wv' => 'application/vnd.wv.csp+wbxml',
      'wvx' => 'video/x-ms-wvx',
      'wz' => 'application/x-wingz',
      'x_b' => 'model/vnd.parasolid.transmit.binary',
      'x_t' => 'model/vnd.parasolid.transmit.text',
      'xbm' => 'image/x-xbm',
      'xcf' => 'image/x-xcf',
      'xcfbz2' => 'image/x-compressed-xcf',
      'xcfgz' => 'image/x-compressed-xcf',
      'xfdf' => 'application/vnd.adobe.xfdf',
      'xhtml' => 'application/xhtml+xml',
      'xls' => 'application/vnd.ms-excel',
      'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
      'xlsm' => 'application/vnd.ms-excel.sheet.macroenabled.12',
      'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'xlt' => 'application/vnd.ms-excel',
      'xml' => 'text/xml',
      'xmt_bin' => 'model/vnd.parasolid.transmit.binary',
      'xmt_txt' => 'model/vnd.parasolid.transmit.text',
      'xpi' => 'application/x-xpinstall',
      'xpm' => 'image/x-xpixmap',
      'xps' => 'application/vnd.ms-xpsdocument',
      'xsl' => 'application/xml',
      'xslt' => 'application/xslt+xml',
      'xul' => 'application/vnd.mozilla.xul+xml',
      'xwd' => 'image/x-xwindowdump',
      'xyz' => 'x-chemical/x-xyz',
      'yaml' => 'text/x-yaml',
      'yml' => 'text/x-yaml',
      'z' => 'application/x-compressed',
      'zip' => 'application/zip'
    );

    return !$extension ? $default : (isset($types[$extension]) ? $types[$extension] : $default);
  }
}
