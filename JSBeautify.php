<?php

namespace JSBeautify;

class JSBeautify
{
    /**
     * Formatter options:
     *   indent_size       - number of characters per indent level
     *   indent_char       - character used for indentation
     *   indent_level      - starting indent level
     *   preserve_newlines - whether to preserve original blank lines
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * Whether <script> tags should be wrapped around the output.
     */
    private bool $addScriptTags = false;

    /**
     * The string repeated to form one indentation level (e.g. "    ").
     */
    private string $indentString;

    /**
     * Current indentation depth (number of indent levels).
     */
    private int $indentLevel;

    /**
     * Whether the last block closed was a do-block.
     */
    private bool $doBlockJustClosed = false;

    /**
     * Accumulated formatted output.
     */
    private string $output = '';

    /**
     * Raw input source code (script tags stripped).
     */
    private string $input;

    /**
     * Stack of parser modes (BLOCK, EXPRESSION, DO_BLOCK).
     *
     * @var string[]
     */
    private array $modes = [];

    /**
     * The current parsing mode.
     */
    private string $currentMode;

    /**
     * Flag indicating we are inside an if/else line.
     */
    private bool $ifLineFlag = false;

    /**
     * The last keyword word token encountered.
     */
    private string $lastWord = '';

    /**
     * Whether we are currently on a var declaration line.
     */
    private bool $varLine = false;

    /**
     * Whether the var line has been tainted by a non-comma operator.
     */
    private bool $varLineTainted = false;

    /**
     * Whether the current token is inside a switch-case label.
     */
    private bool $inCase = false;

    /**
     * Whitespace characters used to skip blanks between tokens.
     */
    private string $whitespace = "\n\r\t ";

    /**
     * Characters that can appear in identifiers / words.
     */
    private string $wordchar = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    /**
     * Digit characters used for numeric literal detection.
     */
    private string $digits = '0123456789';

    /**
     * Current position in the input string.
     */
    private int $parserPos = 0;

    /**
     * Token type of the last processed token.
     */
    private string $lastType = 'TK_START_EXPR';

    /**
     * Text of the last processed token.
     */
    private string $lastText = '';

    /**
     * Text of the current token being processed.
     */
    private string $tokenText = '';

    /**
     * Type of the current token being processed.
     */
    private string $tokenType = '';

    /**
     * All recognised JavaScript operators.
     *
     * @var string[]
     */
    private array $punct = [];

    /**
     * Keywords that should normally appear at the start of a new line.
     *
     * @var string[]
     */
    private array $lineStarters = [];

    /**
     * Prefix hint for the next print action (NONE | NEWLINE | SPACE).
     */
    private string $prefix = 'NONE';

    /**
     * @param string               $sourceText Raw JavaScript source (may include <script> tags).
     * @param array<string, mixed> $options    Optional formatting options.
     */
    public function __construct(string $sourceText, array $options = [])
    {
        $this->options = [
            'indent_size'       => isset($options['indent_size'])       && is_numeric($options['indent_size'])    ? (int)  $options['indent_size']        : 4,
            'indent_char'       => isset($options['indent_char'])       && is_string($options['indent_char'])     ?        $options['indent_char']        : ' ',
            'indent_level'      => isset($options['indent_level'])      && is_numeric($options['indent_level'])   ? (int)  $options['indent_level']       : 0,
            'preserve_newlines' => isset($options['preserve_newlines']) && is_bool($options['preserve_newlines']) ?        $options['preserve_newlines']  : false,
        ];

        $this->indentString = str_repeat($this->options['indent_char'], $this->options['indent_size']);
        $this->indentLevel  = $this->options['indent_level'];

        // Strip <script> tags if present; re-wrap them around the output later.
        $this->input = str_replace(
            ['<script type="text/javascript">', '</script>'],
            '',
            $sourceText
        );

        if (strlen($this->input) !== strlen($sourceText)) {
            // Source contained <script> tags – preserve them in the output.
            $this->input = trim($this->input);
            $this->output .= '<script type="text/javascript">';
            $this->addScriptTags = true;
        }

        $this->input = trim($this->input);

        $this->punct = explode(
            ' ',
            '+ - * / % & ++ -- = += -= *= /= %= == === != !== > < >= <= >> << >>> >>>= >>= <<= && &= | || ! !! , : ? ^ ^= |= ::'
        );

        $this->lineStarters = explode(',', 'continue,try,throw,return,var,if,switch,case,default,for,while,break');

        $this->currentMode = 'BLOCK';
        $this->modes[]     = $this->currentMode;

        // Main tokenisation loop.
        while (true) {
            $t = $this->getNextToken($this->parserPos);
            $this->tokenText = $t[0];
            $this->tokenType = $t[1];

            if ($this->tokenType === 'TK_EOF') {
                break;
            }

            switch ($this->tokenType) {
                case 'TK_START_EXPR':
                    $this->varLine = false;
                    $this->setMode('EXPRESSION');

                    if ($this->lastText === ';' || $this->lastType === 'TK_START_BLOCK') {
                        $this->printNewLine(null);
                    } elseif ($this->lastType === 'TK_END_EXPR' || $this->lastType === 'TK_START_EXPR') {
                        $this->printNewLine();
                    } elseif ($this->lastType !== 'TK_WORD' && $this->lastType !== 'TK_OPERATOR') {
                        $this->printSpace();
                    } elseif (in_array($this->lastWord, $this->lineStarters, true)) {
                        $this->printSpace();
                    }

                    $this->printToken();
                    break;

                case 'TK_END_EXPR':
                    $this->printToken();
                    $this->restoreMode();
                    break;

                case 'TK_START_BLOCK':
                    if ($this->lastWord === 'do') {
                        $this->setMode('DO_BLOCK');
                    } else {
                        $this->setMode('BLOCK');
                    }

                    if ($this->lastType !== 'TK_OPERATOR' && $this->lastType !== 'TK_START_EXPR') {
                        if ($this->lastType === 'TK_START_BLOCK') {
                            $this->printNewLine(null);
                        } else {
                            $this->printSpace();
                        }
                    }

                    $this->printToken();
                    $this->indent();
                    break;

                case 'TK_END_BLOCK':
                    if ($this->lastType === 'TK_START_BLOCK') {
                        $this->trimOutput();
                        $this->unindent();
                    } else {
                        $this->unindent();
                        $this->printNewLine(null);
                    }

                    $this->printToken();
                    $this->restoreMode();
                    break;

                case 'TK_WORD':
                    if ($this->doBlockJustClosed) {
                        $this->printSpace();
                        $this->printToken();
                        $this->printSpace();
                        $this->doBlockJustClosed = false;
                        break;
                    }

                    // Handle case / default labels inside switch blocks.
                    if ($this->tokenText === 'case' || $this->tokenText === 'default') {
                        if ($this->lastText === ':') {
                            $this->removeIndent();
                        } else {
                            $this->unindent();
                            $this->printNewLine(null);
                            $this->indent();
                        }
                        $this->printToken();
                        $this->inCase = true;
                        break;
                    }

                    $this->prefix = 'NONE';

                    if ($this->lastType === 'TK_END_BLOCK') {
                        if (!in_array(strtolower($this->tokenText), ['else', 'catch', 'finally'], true)) {
                            $this->prefix = 'NEWLINE';
                        } else {
                            $this->prefix = 'SPACE';
                            $this->printSpace();
                        }
                    } elseif (
                        $this->lastType === 'TK_SEMICOLON'
                        && ($this->currentMode === 'BLOCK' || $this->currentMode === 'DO_BLOCK')
                    ) {
                        $this->prefix = 'NEWLINE';
                    } elseif ($this->lastType === 'TK_SEMICOLON' && $this->currentMode === 'EXPRESSION') {
                        $this->prefix = 'SPACE';
                    } elseif ($this->lastType === 'TK_STRING') {
                        $this->prefix = 'NEWLINE';
                    } elseif ($this->lastType === 'TK_WORD') {
                        $this->prefix = 'SPACE';
                    } elseif ($this->lastType === 'TK_START_BLOCK') {
                        $this->prefix = 'NEWLINE';
                    } elseif ($this->lastType === 'TK_END_EXPR') {
                        $this->printSpace();
                        $this->prefix = 'NEWLINE';
                    }

                    if (
                        $this->lastType !== 'TK_END_BLOCK'
                        && in_array(strtolower($this->tokenText), ['else', 'catch', 'finally'], true)
                    ) {
                        $this->printNewLine(null);
                    } elseif (
                        in_array($this->tokenText, $this->lineStarters, true)
                        || $this->prefix === 'NEWLINE'
                    ) {
                        if ($this->lastText === 'else') {
                            $this->printSpace();
                        } elseif (
                            ($this->lastType === 'TK_START_EXPR' || $this->lastText === '=' || $this->lastText === ',')
                            && $this->tokenText === 'function'
                        ) {
                            // function expression – no space needed
                        } elseif (
                            $this->lastType === 'TK_WORD'
                            && ($this->lastText === 'return' || $this->lastText === 'throw')
                        ) {
                            $this->printSpace();
                        } elseif ($this->lastType !== 'TK_END_EXPR') {
                            if (
                                ($this->lastType !== 'TK_START_EXPR' || $this->tokenText !== 'var')
                                && $this->lastText !== ':'
                            ) {
                                if (
                                    $this->tokenText === 'if'
                                    && $this->lastType === 'TK_WORD'
                                    && $this->lastWord === 'else'
                                ) {
                                    $this->printSpace();
                                } else {
                                    $this->printNewLine(null);
                                }
                            }
                        } else {
                            if (
                                in_array($this->tokenText, $this->lineStarters, true)
                                && $this->lastText !== ')'
                            ) {
                                $this->printNewLine(null);
                            }
                        }
                    } elseif ($this->prefix === 'SPACE') {
                        $this->printSpace();
                    }

                    $this->printToken();
                    $this->lastWord = $this->tokenText;

                    if ($this->tokenText === 'var') {
                        $this->varLine         = true;
                        $this->varLineTainted  = false;
                    }

                    if ($this->tokenText === 'if' || $this->tokenText === 'else') {
                        $this->ifLineFlag = true;
                    }
                    break;

                case 'TK_SEMICOLON':
                    $this->printToken();
                    $this->varLine = false;
                    break;

                case 'TK_STRING':
                    if (
                        $this->lastType === 'TK_START_BLOCK'
                        || $this->lastType === 'TK_END_BLOCK'
                        || $this->lastType === 'TK_SEMICOLON'
                    ) {
                        $this->printNewLine(null);
                    } elseif ($this->lastType === 'TK_WORD') {
                        $this->printSpace();
                    }

                    $this->printToken();
                    break;

                case 'TK_OPERATOR':
                    $startDelim = true;
                    $endDelim   = true;

                    // Track whether we're still on a var declaration line.
                    if ($this->varLine && $this->tokenText !== ',') {
                        $this->varLineTainted = true;
                        if ($this->tokenText === ':') {
                            $this->varLine = false;
                        }
                    }

                    // A comma inside an expression resets the tainted flag.
                    if ($this->varLine && $this->tokenText === ',' && $this->currentMode === 'EXPRESSION') {
                        $this->varLineTainted = false;
                    }

                    // Colon after a case label – print and start a new line.
                    if ($this->tokenText === ':' && $this->inCase) {
                        $this->printToken();
                        $this->printNewLine(null);
                        $this->inCase = false;
                        break;
                    }

                    // Scope-resolution operator – no surrounding spaces.
                    if ($this->tokenText === '::') {
                        $this->printToken();
                        break;
                    }

                    // Comma handling – context-aware spacing and newlines.
                    if ($this->tokenText === ',') {
                        if ($this->varLine) {
                            if ($this->varLineTainted) {
                                $this->printToken();
                                $this->printNewLine(null);
                                $this->varLineTainted = false;
                            } else {
                                $this->printToken();
                                $this->printSpace();
                            }
                        } elseif ($this->lastType === 'TK_END_BLOCK') {
                            $this->printToken();
                            $this->printNewLine(null);
                        } else {
                            if ($this->currentMode === 'BLOCK') {
                                $this->printToken();
                                $this->printNewLine(null);
                            } else {
                                $this->printToken();
                                $this->printSpace();
                            }
                        }
                        break;
                    }

                    // Increment / decrement operator spacing.
                    if ($this->tokenText === '--' || $this->tokenText === '++') {
                        if ($this->lastText === ';') {
                            if ($this->currentMode === 'BLOCK') {
                                $this->printNewLine(null);
                            }
                            $startDelim = true;
                            $endDelim   = false;
                        } else {
                            if ($this->lastText === '{') {
                                $this->printNewLine(null);
                            }
                            $startDelim = false;
                            $endDelim   = false;
                        }
                    } elseif (
                        in_array($this->tokenText, ['!', '+', '-'], true)
                        && in_array($this->lastText, ['return', 'case'], true)
                    ) {
                        $startDelim = true;
                        $endDelim   = false;
                    } elseif (
                        in_array($this->tokenText, ['!', '+', '-'], true)
                        && $this->lastType === 'TK_START_EXPR'
                    ) {
                        // Unary operator right after an opening bracket – no spaces.
                        $startDelim = false;
                        $endDelim   = false;
                    } elseif ($this->lastType === 'TK_OPERATOR') {
                        $startDelim = false;
                        $endDelim   = false;
                    } elseif ($this->lastType === 'TK_END_EXPR') {
                        $startDelim = true;
                        $endDelim   = true;
                    } elseif ($this->tokenText === '.') {
                        // Member-access dot – no surrounding spaces.
                        $startDelim = false;
                        $endDelim   = false;
                    } elseif ($this->tokenText === ':') {
                        // Ternary colon gets a leading space; object-literal colon does not.
                        $startDelim = $this->isTernaryOp();
                    }

                    if ($startDelim) {
                        $this->printSpace();
                    }
                    $this->printToken();
                    if ($endDelim) {
                        $this->printSpace();
                    }
                    break;

                case 'TK_BLOCK_COMMENT':
                    $this->printNewLine(null);
                    $this->printToken();
                    $this->printNewLine(null);
                    break;

                case 'TK_COMMENT':
                    $this->printSpace();
                    $this->printToken();
                    $this->printNewLine(null);
                    break;

                case 'TK_UNKNOWN':
                    if ($this->lastText !== $this->tokenText) {
                        if (
                            $this->lastType === 'TK_SEMICOLON'
                            || $this->lastType === 'TK_START_BLOCK'
                        ) {
                            $this->printNewLine();
                        }
                        $this->printToken();
                    }
                    break;
            }

            $this->lastType = $this->tokenType;
            $this->lastText = $this->tokenText;
        }
    }

    /**
     * Returns the fully formatted JavaScript source code.
     */
    public function getResult(): string
    {
        if ($this->addScriptTags) {
            $this->output .= '</script>';
        }

        return $this->output;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reads and classifies the next token from the input starting at $parserPos.
     *
     * @param  int              $parserPos Current read position (passed by reference).
     * @return array{0: string, 1: string} [tokenText, tokenType]
     */
    private function getNextToken(int &$parserPos): array
    {
        $newLines = 0;

        if ($parserPos >= strlen($this->input)) {
            return ['', 'TK_EOF'];
        }

        $c = $this->getInputChar($parserPos);
        $parserPos++;

        // Skip whitespace and count newlines for preserve_newlines support.
        while (strpos($this->whitespace, $c) !== false) {
            if ($parserPos >= strlen($this->input)) {
                return ['', 'TK_EOF'];
            }
            if ($c === "\n") {
                $newLines++;
            }
            $c = $this->getInputChar($parserPos);
            $parserPos++;
        }

        $wantNewLine = false;
        if ($this->options['preserve_newlines']) {
            if ($newLines > 1) {
                for ($i = 0; $i < 2; $i++) {
                    $this->printNewLine($i === 0);
                }
            }
            $wantNewLine = ($newLines === 1);
        }

        // Identifier / keyword / numeric literal.
        if (strpos($this->wordchar, $c) !== false) {
            if ($parserPos < strlen($this->input)) {
                while (strpos($this->wordchar, $this->getInputChar($parserPos)) !== false) {
                    $c .= $this->getInputChar($parserPos);
                    $parserPos++;
                    if ($parserPos === strlen($this->input)) {
                        break;
                    }
                }
            }

            // Handle scientific notation exponents (e.g. 1E+10, 2.5e-3).
            if (
                $parserPos !== strlen($this->input)
                && preg_match('/^[0-9]+[Ee]$/', $c)
                && ($this->getInputChar($parserPos) === '-' || $this->getInputChar($parserPos) === '+')
            ) {
                $sign = $this->getInputChar($parserPos);
                $parserPos++;
                $t = $this->getNextToken($parserPos);
                $c .= $sign . $t[0];
                return [$c, 'TK_WORD'];
            }

            // The 'in' keyword behaves like an operator.
            if ($c === 'in') {
                return [$c, 'TK_OPERATOR'];
            }

            if ($wantNewLine && $this->lastType !== 'TK_OPERATOR' && !$this->ifLineFlag) {
                $this->printNewLine(null);
            }

            return [$c, 'TK_WORD'];
        }

        // Grouping / expression delimiters.
        if ($c === '(' || $c === '[') {
            return [$c, 'TK_START_EXPR'];
        }
        if ($c === ')' || $c === ']') {
            return [$c, 'TK_END_EXPR'];
        }
        if ($c === '{') {
            return [$c, 'TK_START_BLOCK'];
        }
        if ($c === '}') {
            return [$c, 'TK_END_BLOCK'];
        }
        if ($c === ';') {
            return [$c, 'TK_SEMICOLON'];
        }

        // Comment or division / regex handling.
        if ($c === '/') {
            $comment = '';

            // Block comment /* ... */
            if ($this->getInputChar($parserPos) === '*') {
                $parserPos++;
                if ($parserPos < strlen($this->input)) {
                    while (!(
                        $this->getInputChar($parserPos) === '*'
                        && $this->getInputChar($parserPos + 1) > "\0"
                        && $this->getInputChar($parserPos + 1) === '/'
                        && $parserPos < strlen($this->input)
                    )) {
                        $comment .= $this->getInputChar($parserPos);
                        $parserPos++;
                        if ($parserPos >= strlen($this->input)) {
                            break;
                        }
                    }
                }
                $parserPos += 2;
                return ['/*' . $comment . '*/', 'TK_BLOCK_COMMENT'];
            }

            // Single-line comment // ...
            if ($this->getInputChar($parserPos) === '/') {
                $comment = $c;
                while (
                    $this->getInputChar($parserPos) !== "\x0d"
                    && $this->getInputChar($parserPos) !== "\x0a"
                ) {
                    $comment .= $this->getInputChar($parserPos);
                    $parserPos++;
                    if ($parserPos >= strlen($this->input)) {
                        break;
                    }
                }
                $parserPos++;
                if ($wantNewLine) {
                    $this->printNewLine(null);
                }
                return [$comment, 'TK_COMMENT'];
            }
        }

        // jQuery / dollar-sign expression start (e.g. $(...) or $.method()).
        if ($c === '$') {
            if ($this->lastType === 'TK_END_BLOCK') {
                $this->printNewLine();
            }
            $d = $parserPos < strlen($this->input) ? $this->getInputChar($parserPos) : null;
            if ($d === '(' || $this->getInputChar($parserPos) === '.') {
                $parserPos++;
                return [$c . $d, 'TK_START_EXPR'];
            }
        }

        // String literal or regex literal.
        if (
            $c === "'"
            || $c === '"'
            || (
                $c === '/'
                && (
                    ($this->lastType === 'TK_WORD' && $this->lastText === 'return')
                    || in_array($this->lastType, [
                        'TK_START_EXPR',
                        'TK_START_BLOCK',
                        'TK_END_BLOCK',
                        'TK_OPERATOR',
                        'TK_EOF',
                        'TK_SEMICOLON',
                    ], true)
                )
            )
        ) {
            $sep           = $c;
            $esc           = false;
            $resultingString = $c;

            if ($parserPos < strlen($this->input)) {
                if ($sep === '/') {
                    // Regex literal – handle character classes so /[/]/ is parsed correctly.
                    $inCharClass = false;
                    while ($esc || $inCharClass || $this->getInputChar($parserPos) !== $sep) {
                        $resultingString .= $this->getInputChar($parserPos);
                        if (!$esc) {
                            $esc = $this->getInputChar($parserPos) === '\\';
                            if ($this->getInputChar($parserPos) === '[') {
                                $inCharClass = true;
                            } elseif ($this->getInputChar($parserPos) === ']') {
                                $inCharClass = false;
                            }
                        } else {
                            $esc = false;
                        }
                        $parserPos++;
                        if ($parserPos >= strlen($this->input)) {
                            return [$resultingString, 'TK_STRING'];
                        }
                    }
                } else {
                    // Regular string literal.
                    while ($esc || $this->getInputChar($parserPos) !== $sep) {
                        $resultingString .= $this->getInputChar($parserPos);
                        if (!$esc) {
                            $esc = $this->getInputChar($parserPos) === '\\';
                        } else {
                            $esc = false;
                        }
                        $parserPos++;
                        if ($parserPos >= strlen($this->input)) {
                            return [$resultingString, 'TK_STRING'];
                        }
                    }
                }
            }

            $parserPos++;
            $resultingString .= $sep;

            // Append regex flags (e.g. /pattern/gi).
            if ($sep === '/') {
                while (
                    $parserPos < strlen($this->input)
                    && strpos($this->wordchar, $this->getInputChar($parserPos)) !== false
                ) {
                    $resultingString .= $this->getInputChar($parserPos);
                    $parserPos++;
                }
            }

            return [$resultingString, 'TK_STRING'];
        }

        // Preprocessor-style # tokens (e.g. #123= or #123#).
        if ($c === '#') {
            $sharp = '#';
            if (
                $parserPos < strlen($this->input)
                && strpos($this->digits, $this->getInputChar($parserPos)) !== false
            ) {
                do {
                    $c      = $this->getInputChar($parserPos);
                    $sharp .= $c;
                    $parserPos++;
                } while ($parserPos < strlen($this->input) && $c !== '#' && $c !== '=');

                if ($c === '#') {
                    return [$sharp, 'TK_WORD'];
                }

                return [$sharp, 'TK_OPERATOR'];
            }
        }

        // HTML comment open <!-- (used in legacy inline scripts).
        if ($c === '<' && substr($this->input, $parserPos - 1, 3) === '<!--') {
            $parserPos += 3;
            return ['<!--', 'TK_COMMENT'];
        }

        // HTML comment close --> (used in legacy inline scripts).
        if ($c === '-' && substr($this->input, $parserPos - 1, 2) === '-->') {
            $parserPos += 2;
            if ($wantNewLine) {
                $this->printNewLine(null);
            }
            return ['-->', 'TK_COMMENT'];
        }

        // Multi-character operator (greedy match against the operator list).
        if (in_array($c, $this->punct, true)) {
            while (
                $parserPos < strlen($this->input)
                && in_array($c . $this->getInputChar($parserPos), $this->punct, true)
            ) {
                $c .= $this->getInputChar($parserPos);
                $parserPos++;
                if ($parserPos >= strlen($this->input)) {
                    break;
                }
            }
            return [$c, 'TK_OPERATOR'];
        }

        return [$c, 'TK_UNKNOWN'];
    }

    /**
     * Removes trailing spaces and indent strings from the output buffer.
     */
    private function trimOutput(): void
    {
        while (
            strlen($this->output) > 0
            && (
                $this->getOutputChar(strlen($this->output) - 1) === ' '
                || $this->getOutputChar(strlen($this->output) - 1) === $this->indentString
            )
        ) {
            $this->output = substr_replace($this->output, '', strlen($this->output) - 1, 1);
        }
    }

    /**
     * Returns the character at the given position of the output buffer.
     */
    private function getOutputChar(int $index): string
    {
        return substr($this->output, $index, 1);
    }

    /**
     * Returns the character at the given position of the input buffer.
     */
    private function getInputChar(int $index): string
    {
        return substr($this->input, $index, 1);
    }

    /**
     * Appends a newline followed by the current indentation to the output.
     *
     * @param mixed $ignoreRepeated Pass null to always add a newline; any other value
     *                              suppresses a second consecutive newline.
     */
    private function printNewLine(mixed $ignoreRepeated = null): void
    {
        $this->ifLineFlag = false;
        $this->trimOutput();

        if (strlen($this->output) === 0) {
            return;
        }

        if ($this->getOutputChar(strlen($this->output) - 1) !== "\n" || !$ignoreRepeated) {
            $this->output .= PHP_EOL;
        }

        for ($i = 0; $i < $this->indentLevel; $i++) {
            $this->output .= $this->indentString;
        }
    }

    /**
     * Appends a single space to the output if the last character is not already
     * a space, newline, or indent string.
     */
    private function printSpace(): void
    {
        $lastOutput = ' ';
        if (strlen($this->output) > 0) {
            $lastOutput = $this->getOutputChar(strlen($this->output) - 1);
        }

        if ($lastOutput !== ' ' && $lastOutput !== "\n" && $lastOutput !== $this->indentString) {
            $this->output .= ' ';
        }
    }

    /**
     * Appends the current token text to the output.
     */
    private function printToken(): void
    {
        $this->output .= $this->tokenText;
    }

    /**
     * Increases the current indentation level by one.
     */
    private function indent(): void
    {
        $this->indentLevel++;
    }

    /**
     * Decreases the current indentation level by one (minimum 0).
     */
    private function unindent(): void
    {
        if ($this->indentLevel > 0) {
            $this->indentLevel--;
        }
    }

    /**
     * Removes the last indent string from the output if present.
     * Used to align case/default labels within switch blocks.
     */
    private function removeIndent(): void
    {
        if (
            strlen($this->output) > 0
            && $this->getOutputChar(strlen($this->output) - 1) === $this->indentString
        ) {
            $this->output = substr_replace($this->output, '', strlen($this->output) - 1, 1);
        }
    }

    /**
     * Pushes the current mode onto the stack and activates a new mode.
     */
    private function setMode(string $mode): void
    {
        $this->modes[]      = $this->currentMode;
        $this->currentMode  = $mode;
    }

    /**
     * Pops the previous mode from the stack and restores it as the current mode.
     */
    private function restoreMode(): void
    {
        $this->doBlockJustClosed = ($this->currentMode === 'DO_BLOCK');
        $this->currentMode       = (string) array_pop($this->modes);
    }

    /**
     * Determines whether a colon in the output belongs to a ternary expression
     * rather than an object-literal or label.
     *
     * Returns true when the nearest unmatched '?' is found before any '{'.
     */
    private function isTernaryOp(): bool
    {
        $level      = 0;
        $colonCount = 0;

        for ($i = strlen($this->output) - 1; $i >= 0; $i--) {
            switch ($this->getOutputChar($i)) {
                case ':':
                    if ($level === 0) {
                        $colonCount++;
                    }
                    break;

                case '?':
                    if ($level === 0) {
                        if ($colonCount === 0) {
                            return true;
                        }
                        $colonCount--;
                    }
                    break;

                case '{':
                    if ($level === 0) {
                        return false;
                    }
                    $level--;
                    break;

                case '(':
                case '[':
                    $level--;
                    break;

                case ')':
                case ']':
                case '}':
                    $level++;
                    break;
            }
        }

        return false;
    }
}
