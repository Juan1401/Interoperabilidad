import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AccesoNoAutorizado } from './acceso-no-autorizado';

describe('AccesoNoAutorizado', () => {
    let component: AccesoNoAutorizado;
    let fixture: ComponentFixture<AccesoNoAutorizado>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [AccesoNoAutorizado]
        }).compileComponents();

        fixture = TestBed.createComponent(AccesoNoAutorizado);
        component = fixture.componentInstance;
        await fixture.whenStable();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
