<?php

declare(strict_types=1);

namespace App\Doctrine\Query\AST\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function: CAST_TEXT(x) → CAST(x AS TEXT)
 *
 * Used to cast JSON columns to TEXT so that LIKE comparisons work on PostgreSQL.
 */
final class CastAsText extends FunctionNode
{
    public Node|string $value;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'CAST(' . $sqlWalker->walkSimpleArithmeticExpression($this->value) . ' AS TEXT)';
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->value = $parser->SimpleArithmeticExpression();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
