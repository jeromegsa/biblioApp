<?php

namespace Tests\Feature;

use App\Models\Adherent;
use App\Models\Livre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PretsTest extends TestCase
{
    use RefreshDatabase;

    private const JETON_BIBLIOTHECAIRE = 'jeton-bibliothecaire-test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['biblio.token' => self::JETON_BIBLIOTHECAIRE]);
    }

    private function biblio(): static
    {
        return $this->withToken(self::JETON_BIBLIOTHECAIRE);
    }

    private function adherent(string $carte = '00000001'): Adherent
    {
        return Adherent::create(['carte' => $carte, 'nom' => 'Awa Dossou', 'api_token' => 'jeton-'.$carte]);
    }

    private function livre(string $isbn = '9780000000001', int $exemplaires = 5): Livre
    {
        return Livre::create(['isbn' => $isbn, 'titre' => 'Titre '.$isbn, 'exemplaires' => $exemplaires]);
    }

    private function emprunter(string $carte, string $isbn)
    {
        return $this->biblio()->postJson('/api/emprunts', ['carte' => $carte, 'isbn' => $isbn]);
    }

    // R1 : formats, bornes, unicité

    public function test_r1_carte_invalide_ou_en_double_est_refusee(): void
    {
        foreach (['1234567', '123456789', 'abcdefgh'] as $carte) {
            $this->biblio()->postJson('/api/adherents', ['carte' => $carte, 'nom' => 'Awa'])->assertStatus(422);
        }

        $this->biblio()->postJson('/api/adherents', ['carte' => '00000001', 'nom' => 'Awa'])->assertCreated();
        $this->biblio()->postJson('/api/adherents', ['carte' => '00000001', 'nom' => 'Bob'])->assertStatus(422);
    }

    public function test_r1_livre_invalide_est_refuse(): void
    {
        $this->biblio()->postJson('/api/livres', ['isbn' => '978000000000', 'titre' => 'T', 'exemplaires' => 3])->assertStatus(422);

        foreach ([0, 21, '5', 5.5] as $exemplaires) {
            $this->biblio()->postJson('/api/livres', ['isbn' => '9780000000001', 'titre' => 'T', 'exemplaires' => $exemplaires])->assertStatus(422);
        }

        $this->biblio()->postJson('/api/livres', ['isbn' => '9780000000001', 'titre' => 'T', 'exemplaires' => 20])->assertCreated();
        $this->biblio()->postJson('/api/livres', ['isbn' => '9780000000001', 'titre' => 'T', 'exemplaires' => 1])->assertStatus(422);
    }

    // R2 : authentification et rôles

    public function test_r2_sans_jeton_401_et_adherent_sur_action_bibliothecaire_403(): void
    {
        $this->postJson('/api/livres', [])->assertUnauthorized();

        $adherent = $this->adherent();
        $this->withToken($adherent->api_token)
            ->postJson('/api/livres', ['isbn' => '9780000000001', 'titre' => 'T', 'exemplaires' => 3])
            ->assertForbidden();
    }

    public function test_r2_un_adherent_ne_voit_que_ses_emprunts(): void
    {
        $moi = $this->adherent('00000001');
        $this->adherent('00000002');

        $this->withToken($moi->api_token)->getJson('/api/adherents/00000001/emprunts')->assertOk();
        $this->withToken($moi->api_token)->getJson('/api/adherents/00000002/emprunts')->assertForbidden();
    }

    // R3 : échéance à 14 jours

    public function test_r3_echeance_a_quatorze_jours(): void
    {
        $this->travelTo('2026-10-29 10:00:00');
        $this->adherent();
        $this->livre();

        $this->emprunter('00000001', '9780000000001')
            ->assertCreated()
            ->assertJsonPath('date_emprunt', '2026-10-29')
            ->assertJsonPath('date_echeance', '2026-11-12')
            ->assertJsonPath('statut', 'en_cours');
    }

    // R4 : au plus 3 emprunts non rendus

    public function test_r4_quatrieme_emprunt_non_rendu_refuse(): void
    {
        $this->adherent();
        foreach (['1', '2', '3', '4'] as $i) {
            $this->livre('978000000000'.$i);
        }

        foreach (['1', '2', '3'] as $i) {
            $this->emprunter('00000001', '978000000000'.$i)->assertCreated();
        }
        $this->emprunter('00000001', '9780000000004')->assertStatus(409);
    }

    // R5 : retard strictement postérieur à l'échéance

    public function test_r5_retard_bloque_les_nouveaux_emprunts(): void
    {
        $this->travelTo('2026-10-29 10:00:00');
        $this->adherent();
        $this->livre('9780000000001');
        $this->livre('9780000000002');
        $this->emprunter('00000001', '9780000000001')->assertCreated(); // échéance le 2026-11-12

        // Le jour de l'échéance : pas en retard.
        $this->travelTo('2026-11-12 23:00:00');
        $this->biblio()->getJson('/api/adherents/00000001/emprunts')->assertJsonPath('data.0.statut', 'en_cours');

        // Le lendemain : en retard, plus d'emprunt possible.
        $this->travelTo('2026-11-13 08:00:00');
        $this->biblio()->getJson('/api/adherents/00000001/emprunts')->assertJsonPath('data.0.statut', 'en_retard');
        $this->emprunter('00000001', '9780000000002')->assertStatus(409);
    }

    // R6 : plus d'exemplaire disponible

    public function test_r6_livre_sans_exemplaire_disponible_refuse(): void
    {
        $this->adherent('00000001');
        $this->adherent('00000002');
        $this->livre('9780000000001', 1);

        $this->emprunter('00000001', '9780000000001')->assertCreated();
        $this->emprunter('00000002', '9780000000001')->assertStatus(409);

        $this->biblio()->getJson('/api/livres/9780000000001')->assertJsonPath('exemplaires_disponibles', 0);
    }

    // R7 : un emprunt rendu ne peut plus être rendu

    public function test_r7_double_retour_refuse(): void
    {
        $this->travelTo('2026-10-29 10:00:00');
        $this->adherent();
        $this->livre();
        $id = $this->emprunter('00000001', '9780000000001')->json('id');

        $this->biblio()->postJson("/api/emprunts/{$id}/retour")
            ->assertOk()
            ->assertJsonPath('date_retour', '2026-10-29')
            ->assertJsonPath('statut', 'rendu');
        $this->biblio()->postJson("/api/emprunts/{$id}/retour")->assertStatus(409);
    }

    // R8 : tri décroissant et filtre de statut

    public function test_r8_tri_decroissant_et_statut_inconnu_refuse(): void
    {
        $this->adherent();
        $this->livre('9780000000001');
        $this->livre('9780000000002');

        $this->travelTo('2026-10-20 10:00:00');
        $ancien = $this->emprunter('00000001', '9780000000001')->json('id');
        $this->travelTo('2026-10-21 10:00:00');
        $recent = $this->emprunter('00000001', '9780000000002')->json('id');

        $this->biblio()->getJson('/api/adherents/00000001/emprunts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recent)
            ->assertJsonPath('data.1.id', $ancien);

        $this->biblio()->getJson('/api/adherents/00000001/emprunts?statut=foo')->assertStatus(422);
    }
}
