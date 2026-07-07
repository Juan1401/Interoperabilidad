import { Component } from '@angular/core';

@Component({
    standalone: true,
    selector: 'app-footer',
    template: `<div class="layout-footer">
        <a href="https://www.minsalud.gov.co/ihce/Paginas/default.aspx" target="_blank" rel="noopener noreferrer" class="font-bold hover:underline">FHIR R4</a>
    </div>`
})
export class AppFooter { }
