<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

/**
 * PersonName::loose — one person spelled a little differently on the site's
 * lists and on the attendee list.
 */
class PersonNameTest extends TestCase
{
    public function test_accents_punctuation_initials_and_titles_do_not_matter(): void
    {
        $this->assertSame('stephane boisvert', PersonName::loose('Stéphane  Boisvert'));
        $this->assertSame(PersonName::loose('Priyanka Sida'), PersonName::loose('Priyanka P Sida'));
        $this->assertSame(PersonName::loose('Makarand Mane'), PersonName::loose('Makarand G. Mane'));
        $this->assertSame(PersonName::loose('Mahmudul Hasan'), PersonName::loose('Md. Mahmudul Hasan'));
        $this->assertSame(PersonName::loose('Joe A Simpson Jr'), PersonName::loose('Joe A Simpson, Jr.'));
        $this->assertSame(PersonName::loose("Wendie Huis in 't Veld"), PersonName::loose('Wendie Huis in t Veld'));
        $this->assertSame(PersonName::loose('Γιώργος Παπάς'), PersonName::loose('Γιωργος Παπας'));
        $this->assertSame('o brien', PersonName::loose('O&#8217;Brien'), 'an entity from the site, a word on its own');
    }

    public function test_it_never_makes_two_different_names_one(): void
    {
        $this->assertNotSame(PersonName::loose('Rezwan Shiblu'), PersonName::loose('Rezwan Shivlu'));
        $this->assertNotSame(PersonName::loose('Manvi'), PersonName::loose('Manvi Singh'));
        // Hindi vowel signs are the name, not accents.
        $this->assertNotSame(PersonName::loose('सुनील कुमार'), PersonName::loose('सनील कुमार'));
        $this->assertSame('सुनील कुमार', PersonName::loose('सुनील कुमार'));
        // A title or initial stays when dropping it would leave one word.
        $this->assertSame('md jewel', PersonName::loose('Md. Jewel'));
        $this->assertSame('a b', PersonName::loose('A. B.'));
    }
}
