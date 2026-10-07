import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'label'];

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.labelTarget.textContent = 'Lien copié !';
        } catch {
            this.sourceTarget.select();
        }
    }
}
