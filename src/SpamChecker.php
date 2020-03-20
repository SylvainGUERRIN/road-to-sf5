<?php


namespace App;

use App\Entity\Comment;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpamChecker
{
    private $client;
    private $endpoint;

    public function __construct(HttpClientInterface $client, string $akismetKey)
    {
        $this->client = $client;
        $this->endpoint = sprintf('https://%s.rest.akismet.com/1.1/comment-check', $akismetKey);
    }

    /**
     * @param Comment $comment
     * @param array $context
     * @return int
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getSpamScore(Comment $comment, array $context): int
    {
        $response = $this->client->request('POST', $this->endpoint, [
            'body' => array_merge($context, [
                'blog' => 'https://guestbook.example.com',
                'comment_type' => 'comment',
                'comment_author' => $comment->getAuthor(),
                'comment_author_email' => $comment->getEmail(),
                'comment_content' =>$comment->getCreatedAt()->format('c'),
                'blog_lang' => 'en',
                'blog_charset' => 'UFT-8',
                'is_test' => true,
            ])
        ]);

        $headers = $response->getHeaders();
        if('discard' === ($headers['x-askimet-pro-tip'][0] ?? '')){
            return 2;
        }

        $content = $response->getContent();
        if(isset($headers['x-askimet-debug-help'][0])){
            throw new \RuntimeException(sprintf('Unable to check for spam: %s(%s).', $content, $headers['x-askimet-debug-help'][0]));
        }
        return 'true' === $content ? 1: 0;
    }
}
