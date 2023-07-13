<?php

namespace App\Controller;

 // Import classes
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PostsListController extends AbstractController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Route('/', name: 'posts_list')]  // posts-list
    public function displayPosts(RequestStack $requestStack): Response
    {
        // Fetch all posts from the database in descending order of creation date
        $sql = "SELECT * FROM posts ORDER BY date_of_creation DESC";
        $posts = $this->connection->fetchAllAssociative($sql);

        // Check if the 'creator_id' cookie is set in the current request
        $creatorId = $requestStack->getCurrentRequest()->cookies->get('creator_id');
        
        // If 'creator_id' cookie is not set, generate a unique ID, set the cookie, and send the response
        if (!$creatorId) {
            $creatorId = uniqid();
            $response = new Response();
            $response->headers->setCookie(new Cookie('creator_id', $creatorId, time() + (86400 * 365), '/'));
            $response->send();
        }

        // Render the 'list.html.twig' template and pass the data to be displayed
        return $this->render('list.html.twig', [
            'pageTitle' => 'Posts List',
            'pageDescription' => 'Browse through the list of posts',
            'posts' => $posts,
            'creatorId' => $creatorId,
        ]);
    }

    #[Route('/post/delete/{id}', name: 'post_delete', methods: ['GET', 'POST'])]
    public function deletePost(Request $request, string $id): Response
    {
        $creatorId = $request->cookies->get('creator_id');
    
        // Fetch the post from the database with the specified ID and matching 'creator_id'
        $sql = "SELECT * FROM posts WHERE id = :id AND creator_id = :creatorId";
        $post = $this->connection->fetchAssociative($sql, ['id' => $id, 'creatorId' => $creatorId]);
    
        // If the post exists, delete it from the database
        if ($post) {
            $deleteSql = "DELETE FROM posts WHERE id = :id AND creator_id = :creatorId";
            $this->connection->executeStatement($deleteSql, ['id' => $id, 'creatorId' => $creatorId]);
        }
    
        // Return an empty response
        return new Response();
    }
}
