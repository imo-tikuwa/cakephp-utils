<?php
declare(strict_types=1);

namespace Utilities\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * TrimComment command.
 * 指定したソースのphpdoc以外のコメントを削除する
 */
class TrimCommentCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addArgument('src', [
            'required' => true,
            'help' => 'plase input trim target file or dir.',
        ]);
        $parser->addOption('list', [
            'boolean' => true,
            'help' => 'When true, a list of files to be deleted is displayed. (Does not delete)',
        ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->out("TrimCommentCommand start.");

        $src = $args->getArgument('src');

        if (!file_exists($src)) {
            $io->abort("Error because `{$src}` is neither a file nor a directory");
        }


        $files = $this->getFileList($src);

        // listオプションが指定されたときコメント削除対象を一覧表示
        if ($args->getOption('list')) {
            $io->out('trim target is:');
            foreach ($files as $index => $file) {
                $count = $index + 1;
                $io->out(sprintf("% 3d `%s`", $count, $file));
            }

            return static::CODE_SUCCESS;
        }

        // 対象のファイルが10件を超えたら念のためY/nで確認する
        if (count($files) > 10) {
            $answer = $io->askChoice('The number of target files exceeds 10. Do you want to run it?', ['Y', 'n'], 'n');
            if ('Y' !== $answer) {
                $io->out("TrimCommentCommand was interrupted.");
                return static::CODE_SUCCESS;
            }
        }

        if (count($files) === 0) {
            $io->out("TrimCommentCommand target notfound.");
            return static::CODE_SUCCESS;
        }

        foreach ($files as $file) {
            $this->trimComment($io, $file);
        }

        $io->out("TrimCommentCommand end.");
    }

    /**
     * ファイルの一覧を取得
     * ※コメント削除対象はphpファイルのみ
     *
     * @param $path ファイルまたはディレクトリのパス
     * @return array ファイル一覧
     */
    private function getFileList($path)
    {
        if (is_file($path) && substr($path, - strlen('.php')) === '.php') {
            return [$path];
        }

        $file_list = [];
        $files = glob(rtrim($path, DS) . DS . '*');
        foreach ($files as $file) {
            if (is_file($file) && substr($file, - strlen('.php')) === '.php') {
                $file_list[] = $file;
            }
            if (is_dir($file)) {
                $file_list = array_merge($file_list, $this->getFileList($file));
            }
        }

        return $file_list;
    }

    /**
     * 引数のファイルについてコメントを削除する
     * @param ConsoleIo $io ConsoleIo
     * @param $filepath コメントを削除するファイル
     * @return void
     */
    private function trimComment(ConsoleIo $io, $filepath)
    {
        $source = file_get_contents($filepath);
        $comment_replaced_source = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $comment_replaced_source .= $token;
            } else {
                list($id, $text) = $token;
                switch ($id) {
                    case T_COMMENT:
                        // 「//」で始まるコメントは$textに改行を含むので置換対象の文字列にも同様に改行コードを含める
                        // 「/*」、「*/」で囲まれるコメントは$text末尾には改行を含まないので末尾の改行コードは不要
                        $comment_replaced_source .= '// replace marker';
                        if (strpos($text, '//') === 0) {
                            $comment_replaced_source .= "\n";
                        }
                        break;
                    default:
                        $comment_replaced_source .= $text;
                        break;
                }
            }
        }
        $comment_removed_source = preg_replace('/^.*\/\/ replace marker.*$\n/m', '', $comment_replaced_source);

        if (!$io->createFile($filepath, $comment_removed_source, true)) {
            $io->abort("An error occurred while writing `{$filepath}`.");
        }
    }
}
