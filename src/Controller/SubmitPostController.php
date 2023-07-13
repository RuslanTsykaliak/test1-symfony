<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubmitPostController extends AbstractController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Route('/submit-post', name: 'submit_post', methods: ['GET', 'POST'])]
    public function submitPost(Request $request): Response
    {
        $notificationMessage = '';
        // Check if the request method is POST
        if ($request->isMethod('POST')) {
            // Retrieve the post title and content from the request and sanitize them
            $postTitle = htmlspecialchars($request->request->get('title'), ENT_QUOTES, 'UTF-8');
            $postContent = htmlspecialchars($request->request->get('content'), ENT_QUOTES, 'UTF-8');

            // Check the length of the post content
            if (strlen($postContent) > 255) {
                $notificationMessage = 'The maximum post size is set to 255 characters';
            } elseif (empty($postTitle) || empty($postContent)) {
                $notificationMessage = 'Please enter both a post title and content';
            } else {
                // Retrieve the creator ID and check if a post can be made
                $creatorId = $this->getCreatorId($request);

                if ($this->canMakePost($creatorId)) {
                    // Insert the title, content, and creator_id into the database
                    $query = "INSERT INTO posts (title, content, creator_id) VALUES (?, ?, ?)";
                    $rowCount = $this->connection->executeUpdate($query, [$postTitle, $postContent, $creatorId]);

                    // Update the last_creation row time for the author
                    $query2 = "INSERT INTO authors (creator_id, last_creation) VALUES (?, NOW()) 
                               ON CONFLICT (creator_id) DO UPDATE SET last_creation = EXCLUDED.last_creation";
                    $rowCount2 = $this->connection->executeUpdate($query2, [$creatorId]);

                    // Check if the update was successful
                    if ($rowCount > 0 && $rowCount2 !== false && $rowCount2 > 0) {
                        return $this->redirectToRoute('posts_list');
                    } else {
                        echo "Error: Failed to update the last creation";
                    }
                } else {
                    $notificationMessage = 'Notification: You can only make one post every 10 minutes';
                }
            }
        }

        return $this->render('submit.html.twig', [
            'pageTitle' => 'Submit Post',
            'pageDescription' => 'The maximum post content is limited to 255 characters',
            'notificationMessage' => $notificationMessage,
        ]);
    }

    // Helper function to retrieve the creator ID from the request cookies
    private function getCreatorId(Request $request): string
    {
        $creatorId = $request->cookies->get('creator_id');
        if (!$creatorId) {
            $creatorId = uniqid();
            $request->cookies->set('creator_id', $creatorId);
        }
        return $creatorId;
    }

    // Helper function to check if a post can be made based on the last_creation row time
    private function canMakePost(string $creatorId): bool
    {
        $query = "SELECT last_creation FROM authors WHERE creator_id = ?";
        $lastCreation = $this->connection->fetchOne($query, [$creatorId]);

        if ($lastCreation === false) {
            return true; // New user, can make a post
        }

        $currentTime = new \DateTime();
        $lastCreationTime = new \DateTime($lastCreation);

        $interval = $lastCreationTime->diff($currentTime);
        $minutesPassed = $interval->i;

        return $minutesPassed >= 10;
    }
}
?>
