import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { range } from "../../../../utils";
import { ProgramPosuv } from "./ProgramPosuv";
import { připravTabulkuAktivit, SeskupováníAktivit } from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import { useProgramStore } from "../../../../store/program";
import {
  useAktivitaNáhled,
  useAktivity,
} from "../../../../store/program/selektory";

type ProgramTabulkaProps = {};

const ZAČÁTEK_AKTIVIT = 8;

const PROGRAM_ČASY = range(ZAČÁTEK_AKTIVIT, 24);

const indexŘazení = (klíč: string) => {
  const index = GAMECON_KONSTANTY.PROGRAM_ŘAZENÍ_LINIE.findIndex(
    (x) => x === klíč
  );
  return index === -1 ? 1000 : index;
};

export const ProgramTabulka: FunctionComponent<ProgramTabulkaProps> = (
  props
) => {
  const {} = props;

  const urlState = useProgramStore((s) => s.urlState);
  const { aktivity, aktivityPřihlášen } = useAktivity();

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
      {Object.entries(předpřipravenáTabulka)
        .sort((a, b) => indexŘazení(a[0]) - indexŘazení(b[0]))
        .map(([klíč, skupina]) => {
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
                        const aktivitaPřihlášen = aktivityPřihlášen.find(
                          (x) => x.id === aktivita.id
                        ) ?? { id: aktivita.id };

                        const classes: string[] = [];
                        if (aktivitaPřihlášen.prihlaseno) {
                          classes.push("prihlasen");
                        }
                        if (aktivitaPřihlášen.vedu) {
                          classes.push("organizator");
                        }
                        // if (aktivitaPřihlášen.nahradnik) {
                        //   classes.push("nahradnik");
                        // }
                        if (aktivita.vdalsiVlne) {
                          classes.push("vDalsiVlne");
                        }
                        // TODO:
                        // if (aktivita.) {
                        //    classes.push("plno");
                        // }
                        if (aktivita.vBudoucnu) {
                          classes.push("vBudoucnu");
                        }

                        const časOdsazení = hodinOd - posledníAktivitaDo;
                        posledníAktivitaDo = hodinDo;
                        return (
                          <>
                            {range(časOdsazení).map(() => (
                              <td></td>
                            ))}
                            <td colSpan={rozsah}>
                              <div class={classes.join(" ")}>
                                <a
                                  class="programNahled_odkaz"
                                  onClick={(e) => {
                                    e.preventDefault();
                                    useProgramStore.setState(
                                      (s) => {
                                        s.urlState.aktivitaNáhledId =
                                          // TODO: pěkná pyramida -> refactor
                                          aktivita.id;
                                      },
                                      undefined,
                                      "tabulka akitvita klik"
                                    );
                                  }}
                                >
                                  {aktivita.nazev}
                                </a>
                                <span class="program_obsazenost">{` (${
                                  aktivita.obsazenost.f + aktivita.obsazenost.m
                                }/${aktivita.obsazenost.ku})`}</span>
                                {(aktivitaPřihlášen.mistnost || undefined) && (
                                  <div class="program_lokace">
                                    {aktivitaPřihlášen.mistnost}
                                  </div>
                                )}
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

  const aktivitaNáhled = useAktivitaNáhled();

  const programNáhledObalProgramuClass =
    "programNahled_obalProgramu" +
    (aktivitaNáhled ? " programNahled_obalProgramu-zuzeny" : "");

  return (
    <>
      <div class={programNáhledObalProgramuClass}>
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
