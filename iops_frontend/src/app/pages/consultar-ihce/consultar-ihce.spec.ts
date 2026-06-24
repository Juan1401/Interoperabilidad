import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ConsultarIhce } from './consultar-ihce';

describe('ConsultarIhce', () => {
    let component: ConsultarIhce;
    let fixture: ComponentFixture<ConsultarIhce>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [ConsultarIhce]
        }).compileComponents();

        fixture = TestBed.createComponent(ConsultarIhce);
        component = fixture.componentInstance;
        await fixture.whenStable();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
