<?php

/**
 * mtFilenameHelper.
 *
 * @package mtContentPlugin
 * @author  szinya <szinya@mentha.hu>
 */

/**
 * Removes invalid characters from filename.
 *
 * @param  string $filename Filename
 * @return string
 */
function mt_clean_filename($filename)
{
  return preg_replace('/[\\/\\\|:;,+]/', '-', preg_replace('/[?%*"<>&()#\\[\\]=]/', '', $filename));
}

/**
 * Splits filename into 'base', 'dot' and 'extension' parts.
 *
 * @param  string $filename Filename
 * @return array
 */
function mt_parse_filename($filename)
{
  return preg_match('/^(?<base>.*)(?<dot>\\.)(?<extension>.*)$/', $filename, $matches) ? $matches : array(
    'base' => $filename,
    'dot' => '',
    'extension' => ''
  );
}

/**
 * Changes filename to a unique value.
 *
 * @param  string $filename  Filename
 * @param  string $directory Directory to search for existing file
 * @return string
 */
function mt_unique_filename($filename, $directory)
{
  $filename = mt_parse_filename($filename);

  $i = 0;

  do
  {
    $fn = $filename['base'] . ($i ? '-' . $i : '') . $filename['dot'] . $filename['extension'];

    $i++;
  }
  while (file_exists($directory . '/' . $fn));

  return $fn;
}
