import { FunctionComponent } from "preact";
import { Aktivita } from "../../../../api/program";
import { useContext, useRef } from "preact/hooks";
import { range } from "../../../../utils";
import { ProgramURLState } from "../../routing";
import { ProgramPosuv } from "./ProgramPosuv";
import { připravTabulkuAktivit, SeskupováníAktivit } from "./seskupování";

type ProgramTabulkaProps = {
  aktivity: Aktivita[];
};

const ZAČÁTEK_AKTIVIT = 8;

const PROGRAM_ČASY = range(ZAČÁTEK_AKTIVIT, 24);

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = (
  props
) => {
  const { aktivity } = props;
  const { urlState } = useContext(ProgramURLState);

  const aktivityFiltrované = aktivity.filter((x) =>
    urlState.výběr.typ === "můj"
      ? true
      : new Date(x.cas.od).getDay() === urlState.výběr.datum.getDay()
  );

  const seskupPodle =
    urlState.výběr.typ === "můj"
      ? SeskupováníAktivit.den
      : SeskupováníAktivit.linie;

  const předpřipravenáTabulka = připravTabulkuAktivit(
    aktivityFiltrované,
    seskupPodle
  );

  const tabulkaHlavičkaČasy = (
    <tr>
      <th></th>
      {PROGRAM_ČASY.map((čas) => (
        <th>{čas}:00</th>
      ))}
    </tr>
  );

  const tabulkaŽádnéAktivity = (
    <tr>
      <td colSpan={PROGRAM_ČASY.length + 1}>Žádné aktivity tento den</td>
    </tr>
  );

  const tabulkaŘádky = (
    <>
      {Object.entries(předpřipravenáTabulka).map(([klíč, skupina]) => {
        const řádků = Math.max(...skupina.map((x) => x.řádek)) + 1;

        return (
          <>
            {range(řádků).map((řádek) => {
              const klíčSkupiny =
                řádek === 0 ? (
                  <td rowSpan={řádků}>
                    <div class="program_nazevLinie">{klíč}</div>
                  </td>
                ) : (
                  <></>
                );

              let posledníAktivitaDo = ZAČÁTEK_AKTIVIT;
              return (
                <tr>
                  {klíčSkupiny}
                  {skupina
                    .filter((x) => x.řádek === řádek)
                    .map((x) => x.aktivita)
                    .sort((a1, a2) => a1.cas.od - a2.cas.od)
                    .map((aktivita) => {
                      const hodinOd = new Date(aktivita.cas.od).getHours();
                      const hodinDo = new Date(aktivita.cas.do).getHours();
                      const rozsah = hodinDo - hodinOd;

                      const časOdsazení = hodinOd - posledníAktivitaDo;
                      posledníAktivitaDo = hodinDo;
                      return (
                        <>
                          {range(časOdsazení).map(()=><td></td>)}
                          <td colSpan={rozsah}>
                            <div>
                              <a class="programNahled_odkaz">
                                {aktivita.nazev}
                              </a>
                              <span class="program_obsazenost">{` (${
                                aktivita.obsazenost.f + aktivita.obsazenost.m
                              }/${aktivita.obsazenost.ku})`}</span>
                            </div>
                          </td>
                        </>
                      );
                    })}
                </tr>
              );
            })}
          </>
        );
      })}
    </>
  );

  const tabulka = (
    <>
      {tabulkaHlavičkaČasy}
      {aktivityFiltrované.length ? tabulkaŘádky : tabulkaŽádnéAktivity}
    </>
  );

  const obalRef = useRef<HTMLDivElement>(null);

  return (
    <>
      <div class="programNahled_obalProgramu">
        <div class="programPosuv_obal2">
          <div class="programPosuv_obal" ref={obalRef}>
            <table class="program">
              <tbody>{tabulka}</tbody>
            </table>
          </div>
          <ProgramPosuv {...{ obalRef }} />
        </div>
      </div>
    </>
  );
};

ProgramTabulka.displayName = "programNáhled";
