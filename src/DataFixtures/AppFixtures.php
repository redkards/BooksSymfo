<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\Author;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher; 

    public function __construct (UserPasswordHasherInterface $userPasswordHasher) 
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        //creation user normal
        $user = new User();
        $user->setEmail("user@bookapi.com");
        $user->setRoles(["ROLE_USER"]);

        $user->setPassword($this->userPasswordHasher->hashPassword($user,"password"));
            $manager->persist($user);

        //creation d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@bookapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);

        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin,"password"));
            $manager->persist($userAdmin);

        //creation des auteurs
        $listAuthor = [];
        for ($i = 0; $i<10; $i++) {
            //creation de l'auteur
            $author = new Author();
            $author->setFirstName("Prénom ". $i);
            $author->setLastName("Nom ".$i);
            $manager->persist($author); 
            // on sauvegarde l'auteur dans un tableau
            $listAuthor[] = $author;
        }

        //creation des livres
        for ($i = 0; $i<20; $i++) {
            $book = new Book ();
            $book->setTitle("Titre ". $i);
            $book->setCoverText("Quatrième de couverture numéro : " . $i);
            $book->setComment("Commentaire du bibliothécaire ". $i);
            // on lie le livre à un auteur pris au hasard dans le tableau des auteurs.
            $book->setAuthor($listAuthor[array_rand($listAuthor)]);
            $manager->persist($book);
        }

        $manager->flush();
    }

}
