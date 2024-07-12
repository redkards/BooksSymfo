<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

class BookController extends AbstractController
{
    #[OA\Response(
        response: 200,
        description: 'Cette méthode permet de récupérer les livres',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: "La page que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: "Le nombre d'éléments que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Tag(name: 'Books')]
    #[Route('/api/books', name: 'books', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" .$page . "-" .$limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer){
            $item->tag("booksCache");
            // $item->expireAfter(60);
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
                // return $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
                $context = SerializationContext::create()->setGroups(['getBooks']);
                return $serializer->serialize($bookList, 'json', $context);
        });
        // $bookList = $cachePool->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit){
            // return $bookRepository->findAllWithPagination($page,$limit);
        // });
        // $jsonBookList = $serializer->serialize($bookList,'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBookList, Response::HTTP_OK,[], true);
    }

    #[OA\Response(
        response: 200,
        description: 'Cette méthode permet de récupérer un livre',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Tag(name: 'Books')]
    #[Route('/api/books/{id}', name:'detailBook', methods: ['GET'])]
        public function getDetailBook(SerializerInterface $serializer, Book $book): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $context->setVersion("1.0");
        $jsonBook = $serializer->serialize($book, 'json', $context);
        // $jsonBook = $serializer->serialize($book ,'json', ['groups' => 'getBooks']);
        return new JsonResponse($jsonBook, Response::HTTP_OK,[], true);
    }

    #[OA\Response(
        response: 200,
        description: 'Cette méthode permet de supprimer un livre',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Tag(name: 'Books')]
    #[Route ('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
        public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Response(
        response: 200,
        description: 'Cette méthode permet de créer un livre',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Tag(name: 'Books')]
    #[Route('/api/books', name:'createBook', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message:'Vous n\'avez pas les droits suffisants pour créer un livre')]
        public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class,'json');
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }
        $em->persist($book);
        $em->flush();
        $content = $request->toArray(); // On récupère le contenu de la requête sous forme de tableau
        $idAuthor = $content['idAuthor'] ?? -1; // Si idAuthor n'est pas présent dans le tableau $content, on lui attribue la valeur -1
        $book->setAuthor($authorRepository->find($idAuthor)); // On attribue l'auteur au livre
        

        // $jsonBook = $serializer->serialize($book,'json', ['groups'=> 'getBooks']);
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], urlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[OA\Response(
        response: 200,
        description: 'Cette méthode permet de modifier un livre',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Book::class, groups: ['getBooks']))
        )
    )]
    #[OA\Tag(name: 'Books')]
    #[Route("/api/books/{id}", name:"updateBook", methods: ["PUT"])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]
        public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    { 
        $newBook = $serializer->deserialize($request->getContent(), Book::class,'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());
        $errors = $validator->validate($currentBook);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }
        $content = $request->toArray(); // On récupère le contenu de la requête sous forme de tableau
        $idAuthor = $content['idAuthor'] ?? -1; // Si idAuthor n'est pas présent dans le tableau $content, on lui attribue la valeur -1
        $currentBook->setAuthor($authorRepository->find($idAuthor)); // On attribue l'auteur au livre
        $em->persist($currentBook); // On persiste le livre
        $em->flush(); // On enregistre les modifications
        $cache->invalidateTags(["booksCache"]); // On invalide le cache

        
        // $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]);
        // $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        // $content = $request->toArray();
        // $idAuthor = $content['idAuthor'] ?? -1;
        // $updatedBook->setAuthor($authorRepository->find($idAuthor));
        // $em->persist($updatedBook);
        // $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}

