<?php
/**
 * DokuWiki Plugin bujomode (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  John Brooks <john@fastquake.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_bujomode extends DokuWiki_Syntax_Plugin {
    function __construct() {
        $this->bullets = $this->getBullets();
        $this->indent = $this->getConf('indent');

        $this->bulletState = false;
        $this->indentLevel = 0;
    }

    /**
     * @return string Syntax mode type
     */
    function getType() { return 'formatting'; }
    function getAllowedTypes() { return array('container', 'formatting',
                                              'substition', 'protected',
                                              'disabled', 'paragraphs'); }
    function getPType() { return 'stack'; }
    function getSort() { return 191; }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<bujo\b[^\R>]*>\s*\R', $mode, 'plugin_bujomode');
    }

    function getBullets() {
        $bullets = array();
        foreach (explode("\n", $this->getConf('bullets')) as $line) {
            $line = trim($line);
            if ($line === '')
                continue;
            $split = preg_split('/\s+/', $line, 2);
            if (count($split) == 1)
                $bullets[$split[0]] = '';
            else
                $bullets[$split[0]] = $split[1];
        }
        return $bullets;
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</bujo\b[^\R>]*>', 'plugin_bujomode');
        $this->Lexer->addPattern(preg_quote($this->indent), 'plugin_bujomode');

        foreach ($this->bullets as $bullet => $replacement) {
            $this->Lexer->addPattern(preg_quote($bullet), 'plugin_bujomode');
        }
    }

    /**
     * Handle matches of the bujomode syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch ($state) {
            case DOKU_LEXER_UNMATCHED:
            case DOKU_LEXER_MATCHED:
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_EXIT:
                return array($state, $match);
        }

        return false;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    function render($mode, Doku_Renderer $renderer, $indata) {
        list($state, $data) = $indata;

        if ($mode == 'xhtml') {
            switch ($state) {
                case DOKU_LEXER_UNMATCHED:
                    $newline = strpos($data, "\n");
                    if ($this->bulletState && $newline !== false) {
                        $renderer->doc .= $renderer->_xmlEntities(
                                substr($data, 0, $newline));
                        $renderer->doc .= '</bujo-text></bujo-entry>';
                        $renderer->doc .= "<br />";
                        $this->bulletState = false;
                    }
                    $renderer->doc .= $renderer->_xmlEntities(
                            substr($data, $newline));
                    break;
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= '<bujo>';
                    break;
                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</bujo>';
                    break;
                case DOKU_LEXER_MATCHED:
                    /* Matched a bullet */
                    if ($data === $this->indent) {
                        ++$this->indentLevel;
                        break;
                    }

                    $renderer->doc .= '<bujo-entry>';

                    if ($this->indentLevel > 0) {
                        $renderer->doc .=
                            '<bujo-indent>'.
                            str_repeat('&nbsp;', $this->indentLevel*4).
                            '</bujo-indent>';
                        $this->indentLevel = 0;
                    }
                    $renderer->doc .= '<bujo-bullet>';
                    $renderer->doc .= $this->bullets[$data] ?
                                      $this->bullets[$data] : $data;
                    $renderer->doc .= '&nbsp;</bujo-bullet><bujo-text>';
                    $this->bulletState = true;
                    break;
            }
            return true;
        }

        return false;
    }
}

