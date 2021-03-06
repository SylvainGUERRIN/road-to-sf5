<?php


namespace App\MessageHandler;


use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use App\Notification\CommentReviewNotification;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CommentMessageHandler implements MessageHandlerInterface
{
    private $spamChecker;
    private $entityManager;
    private $commentRepository;
    private $bus;
    private $workflow;
    private $mailer;
    private $notifier;
    private $imageOptimizer;
    private $adminEmail;
    private $photoDir;
    private $logger;

    /**
     * CommentMessageHandler constructor.
     * @param EntityManagerInterface $entityManager
     * @param SpamChecker $spamChecker
     * @param CommentRepository $commentRepository
     * @param MessageBusInterface $bus
     * @param WorkflowInterface $commentStateMachine
     * @param NotifierInterface $notifier
     * @param ImageOptimizer $imageOptimizer
     * @param string $photoDir
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        SpamChecker $spamChecker,
        CommentRepository $commentRepository,
        MessageBusInterface $bus,
        WorkflowInterface $commentStateMachine,
        NotifierInterface $notifier,
        //MailerInterface $mailer,
        ImageOptimizer $imageOptimizer,
        //string $adminEmail,
        string $photoDir,
        LoggerInterface $logger = null)
    {
        $this->entityManager = $entityManager;
        $this->spamChecker = $spamChecker;
        $this->commentRepository = $commentRepository;
        $this->bus = $bus;
        $this->workflow = $commentStateMachine;
        //$this->mailer = $mailer;
        //$this->adminEmail = $adminEmail;
        $this->notifier = $notifier;
        $this->logger = $logger;
    }

    /**
     * @param CommentMessage $message
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function __invoke(CommentMessage $message): void
    {
        $comment = $this->commentRepository->find($message->getId());
        if(!$comment){
            return;
        }

        if(2 === $this->spamChecker->getSpamScore($comment, $message->getContext())){
            $comment->setState('spam');
        }else{
            $comment->setState('published');
        }

        if($this->workflow->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = 'accept';
            if (2 === $score) {
                $transition = 'reject_spam';
            } elseif (1 === $score) {
                $transition = 'might_be_spam';
            }
            $this->workflow->apply($comment, $transition);
            $this->entityManager->flush();
            $this->bus->dispatch($message);
        }elseif($this->workflow->can($comment, 'publish') || $this->workflow->can($comment, 'publish_ham')) {
            $notification = new CommentReviewNotification($comment, $message->getReviewUrl());

            /*$this->workflow->apply($comment, $this->workflow->can($comment, 'publish') ? 'publish' : 'publish_ham');
            $this->entityManager->flush();*/
            /*$this->mailer->send((new NotificationEmail())
                ->subject('New comment posted')
                ->htmlTemplate('emails/comment_notification.html.twig')
                ->from($this->adminEmail)
                ->to($this->adminEmail)
                ->context(['comment' => $comment])
            );*/
            $this->notifier->send($notification,
                ...$this->notifier->getAdminRecipients());
        }elseif($this->workflow->can($comment, 'optimize')){
            if($comment->getPhotoFilename()){
                $this->imageOptimizer->resize($this->photoDir.'/'.$comment->getPhotoFilename());
            }
            $this->workflow->apply($comment, 'optimize');
            $this->entityManager->flush();
        }elseif ($this->logger){
            $this->logger->debug('Dropping comment message', [
                'comment' => $comment->getId(),
                'state' => $comment->getState()
            ]);
        }

//        $this->entityManager->flush();
    }

}
