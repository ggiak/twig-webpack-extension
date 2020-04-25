<?php

namespace Fullpipe\TwigWebpackExtension\TokenParser;

use Twig\Error\LoaderError;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class EntryTokenParserJs extends AbstractTokenParser
{
    /**
     * @var string
     */
    private $manifestFile;

    /**
     * @var string
     */
    private $publicPath;

    public function __construct(string $manifestFile, string $publicPath)
    {
        $this->manifestFile = $manifestFile;
        $this->publicPath = $publicPath;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(Token $token)
    {
        $stream = $this->parser->getStream();
        $entryName = $stream->expect(Token::STRING_TYPE)->getValue();
        $defer = $stream->nextIf(/* Token::NAME_TYPE */ 5, 'defer');
        $async = $stream->nextIf(/* Token::NAME_TYPE */ 5, 'async');
        $inline = $stream->nextIf(/* Token::NAME_TYPE */ 5, 'inline');
        $stream->expect(Token::BLOCK_END_TYPE);

        if (!\file_exists($this->manifestFile)) {
            throw new LoaderError('Webpack manifest file not exists.', $token->getLine(), $stream->getSourceContext());
        }

        $manifest = \json_decode(\file_get_contents($this->manifestFile), true);
        $manifestIndex = $entryName.'.js';

        if (!isset($manifest[$manifestIndex])) {
            throw new LoaderError('Webpack js entry '.$entryName.' not exists.', $token->getLine(), $stream->getSourceContext());
        }

        $entryPath = $this->publicPath.$manifest[$manifestIndex];

        if ($inline) {
            $tag = \sprintf(
                '<script type="text/javascript">%s</script>',
                $this->getEntryContent($this->manifestFile, $manifest[$manifestIndex])
            );
        } else {
            $tag = \sprintf(
                '<script type="text/javascript" src="%s"%s></script>',
                $entryPath,
                $defer
                ? ' defer'
                : ($async ? ' async' : '')
            );
        }

        return new TextNode($tag, $token->getLine());
    }

    /**
     * @throws Exception if file does not exists
     */
    public function getEntryContent(string $manifestFile, string $entryFile): ?string
    {
        $dir = \dirname($manifestFile);

        if (!\file_exists($dir.'/'.$entryFile)) {
            throw new LoaderError(\sprintf('Entry file "%s" does not exists.', $dir.'/'.$entryFile));
        }

        return \file_get_contents($dir.'/'.$entryFile);
    }

    /**
     * {@inheritdoc}
     */
    public function getTag(): string
    {
        return 'webpack_entry_js';
    }
}
