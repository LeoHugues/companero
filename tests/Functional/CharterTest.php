<?php

namespace App\Tests\Functional;

use App\Entity\CharterRule;
use App\Entity\Member;

final class CharterTest extends AppTestCase
{
    public function testEveryoneWritesTheCharterAndReadsItAgainWhenItChanges(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/coloc/charte');
        self::assertSelectorCount(6, 'ol[aria-label="La charte de la coloc"] > li');
        $this->client->submitForm('Ajouter la règle', ['charter_rule[text]' => 'On ferme la porte du frigo']);
        self::assertResponseRedirects('/coloc/charte');
        $this->client->followRedirect();
        self::assertSelectorCount(7, 'ol[aria-label="La charte de la coloc"] > li');
        self::assertSelectorTextContains('main', 'On ferme la porte du frigo');
        // Whoever wrote it agrees to it.
        self::assertTrue($this->reload($leo)->hasAcceptedCharter());

        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('main', 'La charte de la coloc a changé');
        $this->client->request('GET', '/coloc/charte');
        $this->client->submitForm('Ça me va');
        self::assertResponseRedirects('/coloc/charte');
        self::assertTrue($this->reload($robin)->hasAcceptedCharter());
        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('main', 'La charte de la coloc a changé');
    }

    public function testARuleIsRewrittenMovedAndRemoved(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $second = $this->rules($leo)[1];

        $this->client->request('GET', '/coloc/charte/'.$second->getId());
        $this->client->submitForm('Enregistrer', ['charter_rule[text]' => 'Je range mes affaires avant de dormir.', 'charter_rule[why]' => '']);
        self::assertResponseRedirects('/coloc/charte');

        $this->client->request('GET', '/coloc/charte/'.$second->getId());
        $this->client->submitForm('Monter');
        $rules = $this->rules($leo);
        self::assertSame('Je range mes affaires avant de dormir.', $rules[0]->getText());
        self::assertNull($rules[0]->getWhy());

        $this->client->request('GET', '/coloc/charte/'.$second->getId());
        $this->client->submitForm('Retirer la règle');
        self::assertResponseRedirects('/coloc/charte');
        self::assertCount(5, $this->rules($leo));
        self::assertSame([0, 1, 2, 3, 4], array_map(static fn (CharterRule $rule): int => $rule->getPosition(), $this->rules($leo)));
    }

    public function testTheRulesOfAnotherHouseholdAreOutOfReach(): void
    {
        $zoe = $this->foundHousehold('Zoé', household: 'Une autre coloc');
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/coloc/charte/'.$this->rules($zoe)[0]->getId());

        self::assertResponseStatusCodeSame(403);
    }

    /** @return list<CharterRule> in their order */
    private function rules(Member $member): array
    {
        $rules = $this->reload($member)->getHousehold()->getCharterRules()->toArray();
        usort($rules, static fn (CharterRule $a, CharterRule $b): int => $a->getPosition() <=> $b->getPosition());

        return $rules;
    }
}
